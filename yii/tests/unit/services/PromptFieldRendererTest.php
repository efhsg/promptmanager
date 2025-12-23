<?php

namespace tests\unit\services;

use app\services\PromptFieldRenderer;
use app\widgets\PathPreviewWidget;
use Codeception\Test\Unit;
use conquer\select2\Select2Widget;
use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionProperty;
use Yii;
use yii\base\InvalidConfigException;
use yii\di\Container;
use yii\web\Controller;
use yii\web\JsExpression;
use yii\web\View;

class PromptFieldRendererTest extends Unit
{
    private PromptFieldRenderer $renderer;

    private View $view;

    private View $previousView;

    private array|false $originalBundles;

    private ?Controller $previousController;

    /**
     * @throws InvalidConfigException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->previousView = Yii::$app->getView();
        $this->view = new View();
        $assetManager = Yii::$app->getAssetManager();
        $this->originalBundles = $assetManager->bundles;
        $assetManager->bundles = false;
        $this->view->setAssetManager($assetManager);
        Yii::$app->set('view', $this->view);
        $this->previousController = Yii::$app->controller;
        Yii::$app->controller = new Controller('test', Yii::$app);
        $this->renderer = new PromptFieldRenderer($this->view);
        $this->resetPathPreviewWidgetState();
    }

    /**
     * @throws InvalidConfigException
     */
    protected function tearDown(): void
    {
        Yii::$container->clear(Select2Widget::class);
        $assetManager = Yii::$app->getAssetManager();
        $assetManager->bundles = $this->originalBundles;
        Yii::$app->set('view', $this->previousView);
        Yii::$app->controller = $this->previousController;
        $this->resetPathPreviewWidgetState();
        parent::tearDown();
    }

    public function testToDeltaJsonEncodesArrayWithOps(): void
    {
        $delta = ['ops' => [['insert' => 'sample']]];

        $result = $this->renderer->toDeltaJson($delta);

        $this->assertNotSame('', $result);

        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertSame($delta, $decoded);
    }

    public function testToDeltaJsonReturnsOriginalWhenDeltaStringProvided(): void
    {
        $deltaString = '{"ops":[{"insert":"existing"}]}';

        $result = $this->renderer->toDeltaJson($deltaString);

        $this->assertNotSame('', $result);

        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertSame(['ops' => [['insert' => 'existing']]], $decoded);
    }

    public function testToDeltaJsonReturnsEmptyStringForUnsupportedValue(): void
    {
        $result = $this->renderer->toDeltaJson('plain');

        $this->assertSame('', $result);
    }

    public function testToDeltaJsonReturnsEmptyStringForAdditionalUnsupportedValues(): void
    {
        $this->assertSame('', $this->renderer->toDeltaJson(null));
        $this->assertSame('', $this->renderer->toDeltaJson(123));
        $this->assertSame('', $this->renderer->toDeltaJson(['not_ops' => []]));
        $this->assertSame('', $this->renderer->toDeltaJson('{"invalid":true}'));
    }

    public function testRenderFieldBuildsTextEditorWithLabelPlaceholder(): void
    {
        $field = [
            'type' => 'text',
            'label' => 'Summary',
            'default' => ['ops' => [['insert' => 'Default']]],
        ];

        $html = $this->renderer->renderField($field, '123');

        $dom = $this->createDom($html);
        $hiddenInput = $this->getElementById($dom, 'hidden-123');
        $this->assertInstanceOf(DOMElement::class, $hiddenInput);
        $this->assertSame('PromptInstanceForm[fields][123]', $hiddenInput->getAttribute('name'));

        $value = $hiddenInput->getAttribute('value');
        $this->assertNotSame('', $value);

        $decodedDefault = json_decode($value, true);
        $this->assertIsArray($decodedDefault);
        $this->assertSame($field['default'], $decodedDefault);

        $config = $this->getEditorConfig($dom, 'editor-123');
        $this->assertArrayHasKey('placeholder', $config);
        $this->assertNotSame('', $config['placeholder']);
        $this->assertStringContainsString('Summary', $config['placeholder']);
    }

    public function testRenderFieldDefaultsToTextEditorWhenTypeMissing(): void
    {
        $field = [
            'label' => 'Description',
            'default' => ['ops' => [['insert' => 'Default text']]],
        ];

        $html = $this->renderer->renderField($field, '321');

        $dom = $this->createDom($html);

        $hiddenInput = $this->getElementById($dom, 'hidden-321');
        $this->assertInstanceOf(DOMElement::class, $hiddenInput);
        $this->assertSame('PromptInstanceForm[fields][321]', $hiddenInput->getAttribute('name'));

        $config = $this->getEditorConfig($dom, 'editor-321');
        $this->assertArrayHasKey('placeholder', $config);
        $this->assertStringContainsString('Description', $config['placeholder']);
    }

    public function testRenderFieldUsesCustomPlaceholderForCodeEditor(): void
    {
        $field = [
            'type' => 'code',
            'label' => 'Algorithm',
            'placeholder' => 'Paste or write code here',
        ];

        $html = $this->renderer->renderField($field, '789');

        $config = $this->getEditorConfig($this->createDom($html), 'editor-789');

        $this->assertSame('Paste or write code here', $config['placeholder']);
    }

    public function testRenderFieldConfiguresSelectWidgetForMultipleSelection(): void
    {
        $capturedConfig = [];

        Yii::$container->set(
            Select2Widget::class,
            function (Container $container, array $params, array $config) use (&$capturedConfig): Select2Widget {
                /** @var Select2Widget&MockObject $widget */
                $widget = $this->getMockBuilder(Select2Widget::class)
                    ->disableOriginalConstructor()
                    ->onlyMethods(['run'])
                    ->getMock();

                Yii::configure($widget, $config);
                $widget->setView($this->view);
                $widget->init();

                $widget->method('run')->willReturnCallback(static function () use (&$capturedConfig, $widget): string {
                    $capturedConfig = [
                        'name' => $widget->name,
                        'options' => $widget->options,
                        'settings' => $widget->settings,
                        'items' => $widget->items,
                        'value' => $widget->value,
                    ];
                    $multiple = !empty($widget->options['multiple']) ? ' multiple' : '';

                    return '<select id="' . ($widget->options['id'] ?? '') . '" name="' . $widget->name . '"' . $multiple . '></select>';
                });

                return $widget;
            }
        );

        $field = [
            'type' => 'multi-select',
            'options' => [
                'a' => 'Alpha',
                'b' => 'Beta',
            ],
            'default' => ['b'],
        ];

        $html = $this->renderer->renderField($field, '45');

        $this->assertSame('PromptInstanceForm[fields][45][]', $capturedConfig['name']);
        $this->assertTrue($capturedConfig['options']['multiple']);
        $this->assertSame($field['options'], $capturedConfig['items']);
        $this->assertSame($field['default'], $capturedConfig['value']);
        $this->assertSame(0, $capturedConfig['settings']['minimumResultsForSearch']);
        $this->assertInstanceOf(JsExpression::class, $capturedConfig['settings']['templateResult']);
        $this->assertInstanceOf(JsExpression::class, $capturedConfig['settings']['templateSelection']);
        $this->assertStringContainsString('id="field-45"', $html);
    }

    public function testRenderFieldConfiguresSelectWidgetForSingleSelection(): void
    {
        $capturedConfig = [];

        Yii::$container->set(
            Select2Widget::class,
            function (Container $container, array $params, array $config) use (&$capturedConfig): Select2Widget {
                /** @var Select2Widget&MockObject $widget */
                $widget = $this->getMockBuilder(Select2Widget::class)
                    ->disableOriginalConstructor()
                    ->onlyMethods(['run'])
                    ->getMock();

                Yii::configure($widget, $config);
                $widget->setView($this->view);
                $widget->init();

                $widget->method('run')->willReturnCallback(
                    static function () use (&$capturedConfig, $widget): string {
                        $capturedConfig = [
                            'name' => $widget->name,
                            'options' => $widget->options,
                            'settings' => $widget->settings,
                            'items' => $widget->items,
                            'value' => $widget->value,
                        ];

                        $multiple = !empty($widget->options['multiple']) ? ' multiple' : '';

                        return '<select id="' . ($widget->options['id'] ?? '') . '" name="' . $widget->name . '"' . $multiple . '></select>';
                    }
                );

                return $widget;
            }
        );

        $field = [
            'type' => 'select',
            'options' => [
                'x' => 'X-Ray',
                'y' => 'Yankee',
            ],
            'default' => 'x',
        ];

        $html = $this->renderer->renderField($field, '46');

        $this->assertSame('PromptInstanceForm[fields][46]', $capturedConfig['name']);
        $this->assertEmpty($capturedConfig['options']['multiple'] ?? null);
        $this->assertSame($field['options'], $capturedConfig['items']);
        $this->assertSame($field['default'], $capturedConfig['value']);
        $this->assertSame(0, $capturedConfig['settings']['minimumResultsForSearch']);
        $this->assertInstanceOf(JsExpression::class, $capturedConfig['settings']['templateResult']);
        $this->assertInstanceOf(JsExpression::class, $capturedConfig['settings']['templateSelection']);
        $this->assertStringContainsString('id="field-46"', $html);
    }

    public function testRenderFieldConfiguresSelectInvertAsSingleSelection(): void
    {
        $capturedConfig = [];

        Yii::$container->set(
            Select2Widget::class,
            function (Container $container, array $params, array $config) use (&$capturedConfig): Select2Widget {
                /** @var Select2Widget&MockObject $widget */
                $widget = $this->getMockBuilder(Select2Widget::class)
                    ->disableOriginalConstructor()
                    ->onlyMethods(['run'])
                    ->getMock();

                Yii::configure($widget, $config);
                $widget->setView($this->view);
                $widget->init();

                $widget->method('run')->willReturnCallback(
                    static function () use (&$capturedConfig, $widget): string {
                        $capturedConfig = [
                            'name' => $widget->name,
                            'options' => $widget->options,
                            'settings' => $widget->settings,
                            'items' => $widget->items,
                            'value' => $widget->value,
                        ];

                        $multiple = !empty($widget->options['multiple']) ? ' multiple' : '';

                        return '<select id="' . ($widget->options['id'] ?? '') . '" name="' . $widget->name . '"' . $multiple . '></select>';
                    }
                );

                return $widget;
            }
        );

        $field = [
            'type' => 'select-invert',
            'options' => [
                'on' => 'On',
                'off' => 'Off',
            ],
            'default' => 'off',
        ];

        $html = $this->renderer->renderField($field, '47');

        $this->assertSame('PromptInstanceForm[fields][47]', $capturedConfig['name']);
        $this->assertEmpty($capturedConfig['options']['multiple'] ?? null);
        $this->assertSame($field['options'], $capturedConfig['items']);
        $this->assertSame($field['default'], $capturedConfig['value']);
        $this->assertSame(0, $capturedConfig['settings']['minimumResultsForSearch']);
        $this->assertInstanceOf(JsExpression::class, $capturedConfig['settings']['templateResult']);
        $this->assertInstanceOf(JsExpression::class, $capturedConfig['settings']['templateSelection']);
        $this->assertStringContainsString('id="field-47"', $html);
    }

    public function testRenderFieldRegistersFileFieldScriptWithCurrentPath(): void
    {
        $field = [
            'type' => 'file',
            'id' => 11,
            'project_id' => '22',
            'default' => '/current/path',
        ];

        $html = $this->renderer->renderField($field, 'file-1');

        $registered = $this->view->js[View::POS_END] ?? [];
        $scripts = array_values($registered);
        $script = implode("\n", array_map('strval', $scripts));

        $this->assertStringContainsString('"modalId":"path-modal-file-1"', $script);
        $this->assertStringContainsString('"pathSelectorId":"path-selector-file-1"', $script);
        $this->assertStringContainsString('"hiddenInputId":"field-file-1"', $script);
        $this->assertStringContainsString('"pathPreviewWrapperId":"path-preview-wrapper-file-1"', $script);
        $this->assertStringContainsString('"projectId":"22"', $script);
        $this->assertStringContainsString('window.PathSelectorField.init', $script);
        $this->assertStringContainsString('name="PromptInstanceForm[fields][file-1]"', $html);
        $this->assertStringContainsString('/current/path', $html);
    }

    public function testRenderFieldFallsBackToTextareaForUnknownType(): void
    {
        $html = $this->renderer->renderField(['type' => 'unknown', 'default' => 'Text'], '900');

        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('PromptInstanceForm[fields][900]', $html);
        $this->assertStringContainsString('Text', $html);
    }

    public function testRenderFieldAddsH2LabelWhenRenderLabelTrue(): void
    {
        $field = [
            'type' => 'text',
            'label' => 'My Field Label',
            'render_label' => 1,
            'default' => '',
        ];

        $html = $this->renderer->renderField($field, '123');

        $this->assertStringContainsString('<h2>My Field Label</h2>', $html);
    }

    public function testRenderFieldAddsH2LabelWhenRenderLabelBoolean(): void
    {
        $field = [
            'type' => 'text',
            'label' => 'My Field Label',
            'render_label' => true,
            'default' => '',
        ];

        $html = $this->renderer->renderField($field, '123');

        $this->assertStringContainsString('<h2>My Field Label</h2>', $html);
    }

    public function testRenderFieldSkipsLabelWhenRenderLabelFalse(): void
    {
        $field = [
            'type' => 'text',
            'label' => 'My Field Label',
            'render_label' => 0,
            'default' => '',
        ];

        $html = $this->renderer->renderField($field, '123');

        $this->assertStringNotContainsString('<h2>', $html);
        $this->assertStringNotContainsString('<h2>My Field Label</h2>', $html);
    }

    public function testRenderFieldSkipsLabelWhenRenderLabelBooleanFalse(): void
    {
        $field = [
            'type' => 'text',
            'label' => 'My Field Label',
            'render_label' => false,
            'default' => '',
        ];

        $html = $this->renderer->renderField($field, '123');

        $this->assertStringNotContainsString('<h2>', $html);
        $this->assertStringNotContainsString('<h2>My Field Label</h2>', $html);
    }

    public function testRenderFieldSkipsLabelWhenRenderLabelMissing(): void
    {
        $field = [
            'type' => 'text',
            'label' => 'My Field Label',
            'default' => '',
        ];

        $html = $this->renderer->renderField($field, '123');

        $this->assertStringNotContainsString('<h2>', $html);
    }

    public function testRenderFieldSkipsLabelWhenLabelEmpty(): void
    {
        $field = [
            'type' => 'text',
            'label' => '',
            'render_label' => 1,
            'default' => '',
        ];

        $html = $this->renderer->renderField($field, '123');

        $this->assertStringNotContainsString('<h2>', $html);
    }

    public function testRenderFieldSkipsLabelWhenLabelWhitespace(): void
    {
        $field = [
            'type' => 'text',
            'label' => '   ',
            'render_label' => 1,
            'default' => '',
        ];

        $html = $this->renderer->renderField($field, '123');

        $this->assertStringNotContainsString('<h2>', $html);
    }

    public function testRenderFieldEscapesLabelHtml(): void
    {
        $field = [
            'type' => 'text',
            'label' => '<script>alert("xss")</script>',
            'render_label' => 1,
            'default' => '',
        ];

        $html = $this->renderer->renderField($field, '123');

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    /**
     * @dataProvider selectInvertFieldProvider
     */
    public function testSelectInvertFieldRendersAsSingleSelect(array $field, string $placeholder, ?string $expectedDefault): void
    {
        $capturedConfig = [];

        Yii::$container->set(
            Select2Widget::class,
            function (Container $container, array $params, array $config) use (&$capturedConfig): Select2Widget {
                /** @var Select2Widget&MockObject $widget */
                $widget = $this->getMockBuilder(Select2Widget::class)
                    ->disableOriginalConstructor()
                    ->onlyMethods(['run'])
                    ->getMock();

                Yii::configure($widget, $config);
                $widget->setView($this->view);
                $widget->init();

                $widget->method('run')->willReturnCallback(
                    static function () use (&$capturedConfig, $widget): string {
                        $capturedConfig = [
                            'name' => $widget->name,
                            'options' => $widget->options,
                            'settings' => $widget->settings,
                            'items' => $widget->items,
                            'value' => $widget->value,
                        ];

                        return '<select id="' . ($widget->options['id'] ?? '') . '" name="' . $widget->name . '"></select>';
                    }
                );

                return $widget;
            }
        );

        $html = $this->renderer->renderField($field, $placeholder);

        $this->assertSame("PromptInstanceForm[fields][$placeholder]", $capturedConfig['name']);
        $this->assertEmpty($capturedConfig['options']['multiple'] ?? null, 'select-invert should NOT be multi-select');
        $this->assertSame($field['options'], $capturedConfig['items']);
        $this->assertSame($expectedDefault, $capturedConfig['value']);
        $this->assertStringContainsString("id=\"field-$placeholder\"", $html);
    }

    public static function selectInvertFieldProvider(): array
    {
        return [
            'Basic select-invert with default' => [
                'field' => [
                    'type' => 'select-invert',
                    'options' => [
                        'shell' => 'Shell',
                        'exxon' => 'Exxon Mobil',
                        'bp' => 'BP',
                    ],
                    'default' => 'shell',
                ],
                'placeholder' => 'companies',
                'expectedDefault' => 'shell',
            ],
            'Select-invert with multi-word options' => [
                'field' => [
                    'type' => 'select-invert',
                    'options' => [
                        'exxon_mobil' => 'Exxon Mobil',
                        'shell' => 'Shell',
                        'total' => 'TotalEnergies',
                        'chevron' => 'Chevron',
                    ],
                    'default' => 'exxon_mobil',
                ],
                'placeholder' => 'energy-companies',
                'expectedDefault' => 'exxon_mobil',
            ],
            'Select-invert without default' => [
                'field' => [
                    'type' => 'select-invert',
                    'options' => [
                        'a' => 'Option A',
                        'b' => 'Option B',
                        'c' => 'Option C',
                    ],
                ],
                'placeholder' => 'no-default',
                'expectedDefault' => null,
            ],
        ];
    }

    private function createDom(string $html): DOMDocument
    {
        $document = new DOMDocument();
        $encodedHtml = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        $document->loadHTML($encodedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        return $document;
    }

    private function getElementById(DOMDocument $document, string $id): ?DOMElement
    {
        $xpath = new DOMXPath($document);
        $nodes = $xpath->query("//*[@id='$id']");
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $element = $nodes->item(0);

        return $element instanceof DOMElement ? $element : null;
    }

    private function getEditorConfig(DOMDocument $document, string $editorId): array
    {
        $element = $this->getElementById($document, $editorId);
        $configRaw = $element?->getAttribute('data-config') ?? '';

        return json_decode($configRaw, true) ?? [];
    }

    private function resetPathPreviewWidgetState(): void
    {
        $property = new ReflectionProperty(PathPreviewWidget::class, 'modalRendered');
        $property->setValue(false);
    }
}
