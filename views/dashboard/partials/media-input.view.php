<?php
$mediaInputId = isset($mediaInputId) ? (string)$mediaInputId : '';
$mediaInputName = isset($mediaInputName) ? (string)$mediaInputName : '';
$mediaInputValue = isset($mediaInputValue) ? (string)$mediaInputValue : '';
$mediaInputPlaceholder = isset($mediaInputPlaceholder) ? (string)$mediaInputPlaceholder : '';
$mediaInputStyle = isset($mediaInputStyle) ? (string)$mediaInputStyle : '';
$mediaInputButtonLabel = isset($mediaInputButtonLabel) ? (string)$mediaInputButtonLabel : 'Kies media';
$mediaInputDisabled = !empty($mediaInputDisabled);

if ($mediaInputId === '' || $mediaInputName === '') {
    return;
}
?>
<div class="row">
    <input
        id="<?php echo htmlspecialchars($mediaInputId); ?>"
        type="text"
        name="<?php echo htmlspecialchars($mediaInputName); ?>"
        value="<?php echo htmlspecialchars($mediaInputValue); ?>"
        placeholder="<?php echo htmlspecialchars($mediaInputPlaceholder); ?>"
        <?php echo $mediaInputStyle !== '' ? 'style="' . htmlspecialchars($mediaInputStyle) . '"' : ''; ?>
        <?php echo $mediaInputDisabled ? 'disabled' : ''; ?>
    >
    <button
        type="button"
        class="secondary js-open-media"
        data-target="<?php echo htmlspecialchars($mediaInputId); ?>"
        <?php echo $mediaInputDisabled ? 'disabled' : ''; ?>
    >
        <?php echo htmlspecialchars($mediaInputButtonLabel); ?>
    </button>
</div>
