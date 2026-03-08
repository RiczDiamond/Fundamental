<style>
    .blog-shell {
        display: grid;
        gap: 12px;
    }
    .blog-topbar {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
    }
    .blog-topbar h2,
    .blog-table-title {
        margin: 0;
    }
    .blog-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }
    .blog-filter-form {
        display: grid;
        gap: 10px;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        margin-top: 10px;
    }
    .blog-filter-form .blog-search {
        grid-column: span 2;
    }
    .blog-filter-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .blog-list-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    .blog-bulk-form {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    .blog-title-cell {
        min-width: 260px;
    }
    .blog-permalink {
        word-break: break-all;
    }
    .blog-row-actions {
        display: grid;
        gap: 6px;
        min-width: 180px;
    }
    .blog-row-actions .row {
        gap: 6px;
    }
    .blog-pagination {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-top: 12px;
    }
    .blog-empty {
        text-align: center;
        padding: 20px 8px;
    }
    @media (max-width: 1100px) {
        .blog-filter-form {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .blog-filter-form .blog-search {
            grid-column: span 3;
        }
    }
    @media (max-width: 760px) {
        .blog-topbar,
        .blog-list-head {
            flex-direction: column;
            align-items: stretch;
        }
        .blog-filter-form {
            grid-template-columns: 1fr;
        }
        .blog-filter-form .blog-search {
            grid-column: span 1;
        }
        .blog-actions,
        .blog-filter-actions,
        .blog-bulk-form {
            width: 100%;
        }
    }
</style>

<div class="blog-shell">
    <div class="card">
        <div class="blog-topbar">
            <div>
                <h2>Blogs</h2>
                <p class="muted" style="margin:6px 0 0;">Workflow met filters, bulk-acties, snelle statuswijziging en paginatie.</p>
            </div>
            <div class="blog-actions">
                <?php if ($canBlogWrite) : ?>
                    <a href="/dashboard/blogs/create">+ Nieuwe blogpost</a>
                    <span class="badge active">write toegestaan</span>
                <?php else : ?>
                    <span class="badge banned">alleen read</span>
                <?php endif; ?>
            </div>
        </div>

        <form method="GET" action="/dashboard/blogs" class="blog-filter-form">
            <input class="blog-search" type="text" name="blog_q" value="<?php echo htmlspecialchars($blogSearch ?? ''); ?>" placeholder="Zoek op titel, slug of status">

            <select name="blog_status">
                <option value="">Alle statussen</option>
                <?php foreach (['draft','published','scheduled','archived'] as $statusOption) : ?>
                    <option value="<?php echo $statusOption; ?>" <?php echo (($blogStatusFilter ?? '') === $statusOption) ? 'selected' : ''; ?>><?php echo $statusOption; ?></option>
                <?php endforeach; ?>
            </select>

            <select name="blog_category">
                <option value="">Alle categorieen</option>
                <?php foreach (($blogCategories ?? []) as $catOption) : ?>
                    <option value="<?php echo htmlspecialchars($catOption); ?>" <?php echo (($blogCategoryFilter ?? '') === $catOption) ? 'selected' : ''; ?>><?php echo htmlspecialchars($catOption); ?></option>
                <?php endforeach; ?>
            </select>

            <select name="blog_sort">
                <option value="newest" <?php echo (($blogSort ?? 'newest') === 'newest') ? 'selected' : ''; ?>>Nieuwste</option>
                <option value="oldest" <?php echo (($blogSort ?? '') === 'oldest') ? 'selected' : ''; ?>>Oudste</option>
                <option value="updated" <?php echo (($blogSort ?? '') === 'updated') ? 'selected' : ''; ?>>Laatst bijgewerkt</option>
                <option value="title_asc" <?php echo (($blogSort ?? '') === 'title_asc') ? 'selected' : ''; ?>>Titel A-Z</option>
                <option value="title_desc" <?php echo (($blogSort ?? '') === 'title_desc') ? 'selected' : ''; ?>>Titel Z-A</option>
                <option value="status" <?php echo (($blogSort ?? '') === 'status') ? 'selected' : ''; ?>>Status</option>
            </select>

            <select name="blog_per_page">
                <?php foreach ([10,20,30,50] as $perPageOption) : ?>
                    <option value="<?php echo (int)$perPageOption; ?>" <?php echo ((int)($blogPerPage ?? 10) === (int)$perPageOption) ? 'selected' : ''; ?>><?php echo (int)$perPageOption; ?> per pagina</option>
                <?php endforeach; ?>
            </select>

            <div class="blog-filter-actions">
                <input type="hidden" name="blog_page" value="1">
                <button type="submit" class="secondary">Zoeken</button>
                <a href="/dashboard/blogs">Reset</a>
            </div>
        </form>

        <div class="small" style="margin-top:8px;">Totaal: <?php echo (int)($blogTotalItems ?? 0); ?> items</div>
    </div>

    <div class="card">
        <div class="blog-list-head">
            <h3 class="blog-table-title">Blogposts</h3>
            <form method="POST" action="/dashboard/blogs" class="blog-bulk-form" id="blog-bulk-form">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="blog_bulk">
                <select name="bulk_action" <?php echo !$canBlogWrite ? 'disabled' : ''; ?>>
                    <option value="">Bulk-actie</option>
                    <option value="draft">Status -> draft</option>
                    <option value="published">Status -> published</option>
                    <option value="scheduled">Status -> scheduled</option>
                    <option value="archived">Status -> archived</option>
                    <option value="delete">Verwijderen</option>
                </select>
                <button type="submit" class="secondary" <?php echo !$canBlogWrite ? 'disabled' : ''; ?>>Toepassen op selectie</button>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all-posts"></th>
                    <th>ID</th>
                    <th>Titel</th>
                    <th>Status</th>
                    <th>Permalink</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($blogPosts)) : ?>
                    <tr>
                        <td colspan="6" class="muted blog-empty">Nog geen blogposts.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($blogPosts as $post) : ?>
                        <tr>
                            <td><input type="checkbox" value="<?php echo (int)$post['id']; ?>" class="row-check"></td>
                            <td><?php echo (int)$post['id']; ?></td>
                            <td class="blog-title-cell">
                                <strong><?php echo htmlspecialchars($post['title'] ?? ''); ?></strong><br>
                                <span class="small">slug: <?php echo htmlspecialchars($post['slug'] ?? ''); ?></span>
                                <?php if (!empty($post['scheduled_at'])) : ?>
                                    <br><span class="small">scheduled: <?php echo htmlspecialchars($post['scheduled_at']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="/dashboard/blogs" class="row">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="action" value="blog_inline_status">
                                    <input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>">
                                    <select name="status" onchange="this.form.submit()" <?php echo !$canBlogWrite ? 'disabled' : ''; ?>>
                                        <?php foreach (['draft','published','scheduled','archived'] as $blogStatus) : ?>
                                            <option value="<?php echo $blogStatus; ?>" <?php echo (($post['status'] ?? 'draft') === $blogStatus) ? 'selected' : ''; ?>><?php echo $blogStatus; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td class="blog-permalink">
                                <a class="small" href="<?php echo htmlspecialchars($post['permalink'] ?? '#'); ?>" target="_blank">
                                    <?php echo htmlspecialchars($post['permalink'] ?? ''); ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($canBlogWrite) : ?>
                                    <div class="blog-row-actions">
                                        <div class="row">
                                            <a href="/dashboard/blogs/edit/<?php echo (int)$post['id']; ?>" class="secondary">Bewerken</a>
                                            <form method="POST" action="/dashboard/blogs" class="row">
                                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                <input type="hidden" name="action" value="blog_duplicate">
                                                <input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>">
                                                <button type="submit" class="secondary">Dupliceren</button>
                                            </form>
                                        </div>
                                        <form method="POST" action="/dashboard/blogs" class="row">
                                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="action" value="blog_delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>">
                                            <button type="submit" class="warn" onclick="return confirm('Weet je zeker dat je deze post wilt verwijderen?');">Verwijderen</button>
                                        </form>
                                    </div>
                                <?php else : ?>
                                    <span class="small">Geen schrijfrechten</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if (($blogPagesTotal ?? 1) > 1) : ?>
            <div class="blog-pagination">
                <?php for ($p = 1; $p <= (int)$blogPagesTotal; $p++) : ?>
                    <?php
                        $query = http_build_query([
                            'blog_q' => $blogSearch ?? '',
                            'blog_status' => $blogStatusFilter ?? '',
                            'blog_category' => $blogCategoryFilter ?? '',
                            'blog_sort' => $blogSort ?? 'newest',
                            'blog_per_page' => $blogPerPage ?? 10,
                            'blog_page' => $p,
                        ]);
                    ?>
                    <a href="/dashboard/blogs?<?php echo htmlspecialchars($query); ?>" class="<?php echo ((int)($blogPage ?? 1) === $p) ? 'badge active' : 'badge'; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    (function () {
        var selectAll = document.getElementById('select-all-posts');
        var bulkForm = document.getElementById('blog-bulk-form');
        var checks = document.querySelectorAll('.row-check');

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checks.forEach(function (checkbox) {
                    checkbox.checked = !!selectAll.checked;
                });
            });
        }

        if (bulkForm) {
            bulkForm.addEventListener('submit', function (event) {
                bulkForm.querySelectorAll('input[name="selected_ids[]"]').forEach(function (el) {
                    el.remove();
                });

                var selected = [];
                checks.forEach(function (checkbox) {
                    if (checkbox.checked) {
                        selected.push(String(checkbox.value || '0'));
                    }
                });

                if (!selected.length) {
                    event.preventDefault();
                    window.alert('Selecteer minimaal 1 blogpost voor een bulk-actie.');
                    return;
                }

                selected.forEach(function (id) {
                    var hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'selected_ids[]';
                    hidden.value = id;
                    bulkForm.appendChild(hidden);
                });
            });
        }
    })();
</script>
