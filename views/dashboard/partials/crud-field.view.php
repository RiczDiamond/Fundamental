<?php
$crudField = isset($crudField) && is_array($crudField) ? $crudField : [];

$crudFieldId = (string)($crudField['id'] ?? '');
$crudFieldName = (string)($crudField['name'] ?? '');
$crudFieldLabel = (string)($crudField['label'] ?? '');
$crudFieldType = (string)($crudField['type'] ?? 'text');
$crudFieldValue = $crudField['value'] ?? '';
$crudFieldPlaceholder = (string)($crudField['placeholder'] ?? '');
$crudFieldRows = (int)($crudField['rows'] ?? 4);
$crudFieldRequired = !empty($crudField['required']);
$crudFieldOptions = isset($crudField['options']) && is_array($crudField['options']) ? $crudField['options'] : [];
$crudFieldHelp = (string)($crudField['help'] ?? '');
$crudFieldInputStyle = (string)($crudField['input_style'] ?? '');
$crudFieldDisabled = !empty($crudField['disabled']);
$crudFieldShowPreview = !empty($crudField['show_preview']);

if ($crudFieldId === '' || $crudFieldName === '') {
    return;
}
?>
<div class="content-editor-field">
    <?php if ($crudFieldLabel !== '') : ?>
        <label for="<?php echo htmlspecialchars($crudFieldId); ?>"><?php echo htmlspecialchars($crudFieldLabel); ?></label>
    <?php endif; ?>

    <?php if ($crudFieldType === 'textarea') : ?>
        <textarea
            class="js-wysiwyg"
            id="<?php echo htmlspecialchars($crudFieldId); ?>"
            name="<?php echo htmlspecialchars($crudFieldName); ?>"
            rows="<?php echo max(2, $crudFieldRows); ?>"
            <?php echo $crudFieldPlaceholder !== '' ? 'placeholder="' . htmlspecialchars($crudFieldPlaceholder) . '"' : ''; ?>
            <?php echo $crudFieldRequired ? 'required' : ''; ?>
            <?php echo $crudFieldDisabled ? 'disabled' : ''; ?>
        ><?php echo htmlspecialchars((string)$crudFieldValue); ?></textarea>
    <?php elseif ($crudFieldType === 'select') : ?>
        <select id="<?php echo htmlspecialchars($crudFieldId); ?>" name="<?php echo htmlspecialchars($crudFieldName); ?>" <?php echo $crudFieldDisabled ? 'disabled' : ''; ?>>
            <?php foreach ($crudFieldOptions as $optValue => $optLabel) : ?>
                <option value="<?php echo htmlspecialchars((string)$optValue); ?>" <?php echo (string)$crudFieldValue === (string)$optValue ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string)$optLabel); ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php elseif ($crudFieldType === 'datetime') : ?>
        <input
            id="<?php echo htmlspecialchars($crudFieldId); ?>"
            type="datetime-local"
            name="<?php echo htmlspecialchars($crudFieldName); ?>"
            value="<?php echo htmlspecialchars((string)$crudFieldValue); ?>"
            <?php echo $crudFieldRequired ? 'required' : ''; ?>
            <?php echo $crudFieldDisabled ? 'disabled' : ''; ?>
        >
    <?php elseif ($crudFieldType === 'media') : ?>
        <?php
            $mediaInputId = $crudFieldId;
            $mediaInputName = $crudFieldName;
            $mediaInputValue = (string)$crudFieldValue;
            $mediaInputPlaceholder = $crudFieldPlaceholder;
            $mediaInputStyle = $crudFieldInputStyle;
            $mediaInputDisabled = $crudFieldDisabled;
            require __DIR__ . '/media-input.view.php';

            if ($crudFieldShowPreview) {
                $imagePreviewWrapId = $crudFieldId . '-preview-wrap';
                $imagePreviewInputId = $crudFieldId;
                $imagePreviewImgId = $crudFieldId . '-preview';
                $imagePreviewAlt = $crudFieldLabel !== '' ? ($crudFieldLabel . ' preview') : 'Image preview';
                require __DIR__ . '/image-preview.view.php';
            }
        ?>
    <?php else : ?>
        <input
            id="<?php echo htmlspecialchars($crudFieldId); ?>"
            type="text"
            name="<?php echo htmlspecialchars($crudFieldName); ?>"
            value="<?php echo htmlspecialchars((string)$crudFieldValue); ?>"
            <?php echo $crudFieldPlaceholder !== '' ? 'placeholder="' . htmlspecialchars($crudFieldPlaceholder) . '"' : ''; ?>
            <?php echo $crudFieldRequired ? 'required' : ''; ?>
            <?php echo $crudFieldInputStyle !== '' ? 'style="' . htmlspecialchars($crudFieldInputStyle) . '"' : ''; ?>
            <?php echo $crudFieldDisabled ? 'disabled' : ''; ?>
        >
    <?php endif; ?>

    <?php if ($crudFieldHelp !== '') : ?>
        <p class="small" style="margin:0;"><?php echo htmlspecialchars($crudFieldHelp); ?></p>
    <?php endif; ?>
</div>
