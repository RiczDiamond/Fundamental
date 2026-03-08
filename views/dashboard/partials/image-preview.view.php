<?php
$imagePreviewWrapId = isset($imagePreviewWrapId) ? (string)$imagePreviewWrapId : '';
$imagePreviewInputId = isset($imagePreviewInputId) ? (string)$imagePreviewInputId : '';
$imagePreviewImgId = isset($imagePreviewImgId) ? (string)$imagePreviewImgId : '';
$imagePreviewAlt = isset($imagePreviewAlt) ? (string)$imagePreviewAlt : 'Image preview';

if ($imagePreviewWrapId === '' || $imagePreviewInputId === '' || $imagePreviewImgId === '') {
    return;
}
?>
<div
    id="<?php echo htmlspecialchars($imagePreviewWrapId); ?>"
    class="dashboard-image-preview is-empty js-image-preview"
    data-input-id="<?php echo htmlspecialchars($imagePreviewInputId); ?>"
    data-img-id="<?php echo htmlspecialchars($imagePreviewImgId); ?>"
>
    <img id="<?php echo htmlspecialchars($imagePreviewImgId); ?>" src="" alt="<?php echo htmlspecialchars($imagePreviewAlt); ?>">
</div>
