<?php
$footerYear = date('Y');
?>
<style>
    .ff-footer {
        margin-top: 28px;
        background: #0b1325;
        color: #b9c8dd;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
    }
    .ff-footer .ff-inner {
        max-width: 1100px;
        margin: 0 auto;
        padding: 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        font-size: 13px;
    }
    .ff-links {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }
    .ff-footer a {
        color: #9ec7ff;
        text-decoration: none;
    }
    .ff-footer a:hover,
    .ff-footer a:focus-visible {
        color: #ffffff;
        text-decoration: underline;
        text-underline-offset: 2px;
    }
    @media (max-width: 640px) {
        .ff-footer .ff-inner {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>
<footer class="ff-footer">
    <div class="ff-inner">
        <div>© <?php echo $footerYear; ?> Fundamental CMS</div>
        <div class="ff-links">
            <a href="/blog">Blog</a> ·
            <a href="/dashboard/overview">Dashboard</a>
        </div>
    </div>
</footer>
