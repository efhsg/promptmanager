import Bold from '@ckeditor/ckeditor5-basic-styles/src/bold';
import Italic from '@ckeditor/ckeditor5-basic-styles/src/italic';
import ClassicEditorBase from '@ckeditor/ckeditor5-editor-classic/src/classiceditor';
import Essentials from '@ckeditor/ckeditor5-essentials/src/essentials';

// Add custom plugins here if needed
// import MyCustomPlugin from './plugins/mycustomplugin';

export default class ClassicEditor extends ClassicEditorBase {}

ClassicEditor.builtinPlugins = [
    Essentials,
    Bold,
    Italic,
    // MyCustomPlugin
];

ClassicEditor.defaultConfig = {
    toolbar: {
        items: ['bold', 'italic', 'undo', 'redo'],
    },
};
