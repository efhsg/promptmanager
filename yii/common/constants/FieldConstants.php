<?php

namespace common\constants;

class FieldConstants
{
    public const TYPES = ['text', 'select', 'multi-select', 'code', 'select-invert', 'file', 'directory', 'string', 'number'];
    public const OPTION_FIELD_TYPES = ['select', 'multi-select', 'select-invert'];
    public const CONTENT_FIELD_TYPES = ['text', 'code', 'select-invert'];
    public const PATH_FIELD_TYPES = ['file', 'directory'];
    public const PATH_PREVIEWABLE_FIELD_TYPES = ['file'];
    public const INLINE_FIELD_TYPES = ['string', 'number'];
    public const NO_OPTION_FIELD_TYPES = 'input, textarea, select, code';
}
