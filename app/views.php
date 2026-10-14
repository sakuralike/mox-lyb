<?php
declare(strict_types=1);

function render_page(string $title, callable $body, array $options = []): void
{
    $settings = !empty($options['settings_disabled']) ? site_settings_defaults() : site_settings();
    $siteName = (string) $settings['site_name'];
    $logoUrl = !empty($options['settings_disabled']) ? null : site_logo_url($settings);
    $active = (string) ($options['active'] ?? '');
    $isAdmin = (bool) ($options['admin'] ?? false);
    $backgrounds = background_urls();
    ?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="description" content="<?= h((string) $settings['site_description']) ?>">
    <title><?= h($title) ?> · <?= h($siteName) ?></title>
    <?php if ($logoUrl !== null): ?>
        <link rel="icon" href="<?= h($logoUrl) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= h(app_url('/assets/app.css?v=' . substr(hash_file('sha256', APP_ROOT . '/assets/app.css'), 0, 12))) ?>">
    <script src="<?= h(app_url('/assets/app.js?v=' . substr(hash_file('sha256', APP_ROOT . '/assets/app.js'), 0, 12))) ?>" defer></script>
</head>
<body>
<div class="ambient-scene" aria-hidden="true" data-backgrounds="<?= h(json_encode($backgrounds, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) ?>">
    <div class="ambient-layer" data-gradient-layer><img data-background-image src="<?= h($backgrounds[0]) ?>" alt="" fetchpriority="high"></div>
    <div class="ambient-layer" data-gradient-layer data-visible="false"><img data-background-image alt=""></div>
    <div class="ambient-tint"></div>
    <div class="ambient-sparkles"></div>
</div>
<a class="skip-link" href="#main">跳至正文</a>
<div class="shell">
    <header class="site-header">
        <a class="brand" href="<?= h(app_url('/')) ?>" aria-label="<?= h($siteName) ?>首页">
            <span class="brand-mark<?= $logoUrl !== null ? ' brand-mark-custom' : '' ?>" aria-hidden="true">
                <?php if ($logoUrl !== null): ?>
                    <img src="<?= h($logoUrl) ?>" alt="">
                <?php else: ?>
                    <svg viewBox="0 0 40 40" fill="none">
                        <path d="M7 14 12 5l8 7 8-7 5 9v14c0 8-26 8-26 0Z" fill="currentColor"/>
                        <path d="M14 22v2m12-2v2m-9 4q3 3 6 0" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>
                        <path d="m16 15 4 4 4-4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                <?php endif; ?>
            </span>
            <span>
                <span class="brand-name"><?= h($siteName) ?></span>
                <span class="brand-sub">A LITTLE SPACE FOR YOU</span>
            </span>
        </a>
        <nav class="site-nav" aria-label="主导航">
            <a href="<?= h(app_url('/')) ?>"<?= $active === 'home' ? ' class="nav-current" aria-current="page"' : '' ?>>留言墙</a>
            <?php if ($isAdmin): ?>
                <a href="<?= h(app_url('/admin/index.php')) ?>"<?= $active === 'admin' ? ' class="nav-current" aria-current="page"' : '' ?>>管理后台</a>
                <form class="nav-form" action="<?= h(app_url('/admin/index.php')) ?>" method="post">
                    <input type="hidden" name="action" value="logout">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <button class="nav-button" type="submit">退出</button>
                </form>
            <?php else: ?>
                <a href="<?= h(app_url('/admin/index.php')) ?>"<?= $active === 'login' ? ' class="nav-current" aria-current="page"' : '' ?>>管理入口 <span aria-hidden="true">↗</span></a>
            <?php endif; ?>
        </nav>
    </header>
    <main id="main">
        <?php $body(); ?>
    </main>
    <footer class="site-footer">
        <p class="footer-note"><span aria-hidden="true">♡</span> <?= h((string) $settings['footer_text']) ?></p>
        <p><?= h($siteName) ?></p>
    </footer>
</div>
</body>
</html>
    <?php
}

function render_notices(array $data): void
{
    $notice = $data['notice'] ?? null;
    if (is_array($notice) && (string) ($notice['text'] ?? '') !== '') {
        $type = (string) ($notice['type'] ?? 'info');
        $class = match ($type) {
            'success' => '',
            'error' => ' flash-error',
            default => ' flash-info',
        };
        ?>
        <div class="flash<?= h($class) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><?= h((string) $notice['text']) ?></div>
        <?php
    }
    $errors = $data['errors'] ?? [];
    if (is_array($errors) && $errors !== []) {
        ?>
        <div class="flash flash-error" role="alert">
            <strong>请检查以下内容</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= h((string) $error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }
}

function render_pagination(int $page, int $pages, string $path, array $query = []): void
{
    if ($pages <= 1) {
        return;
    }
    $link = static fn (int $number): string => app_url($path . '?' . http_build_query(array_merge($query, ['page' => $number])));
    $first = max(1, $page - 2);
    $last = min($pages, $page + 2);
    ?>
    <nav class="pagination" aria-label="留言分页">
        <?php if ($page > 1): ?>
            <a href="<?= h($link($page - 1)) ?>" rel="prev" aria-label="上一页">←</a>
        <?php else: ?>
            <span class="page-disabled" aria-hidden="true">←</span>
        <?php endif; ?>
        <?php if ($first > 1): ?>
            <a href="<?= h($link(1)) ?>" aria-label="第 1 页">1</a>
            <?php if ($first > 2): ?><span aria-hidden="true">…</span><?php endif; ?>
        <?php endif; ?>
        <?php for ($number = $first; $number <= $last; $number++): ?>
            <?php if ($number === $page): ?>
                <span aria-current="page" aria-label="当前第 <?= h($number) ?> 页"><?= h($number) ?></span>
            <?php else: ?>
                <a href="<?= h($link($number)) ?>" aria-label="第 <?= h($number) ?> 页"><?= h($number) ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($last < $pages): ?>
            <?php if ($last < $pages - 1): ?><span aria-hidden="true">…</span><?php endif; ?>
            <a href="<?= h($link($pages)) ?>" aria-label="第 <?= h($pages) ?> 页"><?= h($pages) ?></a>
        <?php endif; ?>
        <?php if ($page < $pages): ?>
            <a href="<?= h($link($page + 1)) ?>" rel="next" aria-label="下一页">→</a>
        <?php else: ?>
            <span class="page-disabled" aria-hidden="true">→</span>
        <?php endif; ?>
    </nav>
    <?php
}

function render_message_body(array $message): void
{
    ?>
    <p class="message-content"><?= nl2br(h((string) ($message['message'] ?? ''))) ?></p>
    <?php if (!empty($message['has_image'])): ?>
        <a class="message-image" href="<?= h(app_url('/image.php?id=' . (int) $message['id'])) ?>" target="_blank" rel="noopener noreferrer" aria-label="在新窗口查看留言图片">
            <img src="<?= h(app_url('/image.php?id=' . (int) $message['id'])) ?>" alt="<?= h((string) ($message['name'] ?? '访客')) ?>上传的留言图片" loading="lazy" decoding="async">
        </a>
    <?php endif; ?>
    <?php if ((string) ($message['reply'] ?? '') !== ''): ?>
        <div class="reply">
            <div class="reply-heading">
                <span>站长回复</span>
                <?php if (!empty($message['replied_at'])): ?>
                    <time><?= h((string) $message['replied_at']) ?></time>
                <?php endif; ?>
            </div>
            <p class="message-content"><?= nl2br(h((string) $message['reply'])) ?></p>
        </div>
    <?php endif; ?>
    <?php
}

function render_message_author(array $message): void
{
    $name = (string) ($message['name'] ?? '访客');
    $initial = preg_match('/\S/u', $name, $match) === 1 ? $match[0] : '言';
    ?>
    <div class="author">
        <span class="avatar" aria-hidden="true"><?= h($initial) ?></span>
        <div>
            <span class="author-name"><?= h($name) ?></span>
            <time class="message-meta"><?= h((string) ($message['created_at'] ?? '')) ?></time>
        </div>
    </div>
    <?php
}

function render_home(array $data): void
{
    $settings = site_settings();
    $values = is_array($data['values'] ?? null) ? $data['values'] : [];
    $messages = is_array($data['messages'] ?? null) ? $data['messages'] : [];
    render_page('留言墙', static function () use ($data, $values, $messages, $settings): void {
        ?>
        <section class="hero reveal" aria-labelledby="home-heading">
            <div class="hero-intro">
                <p class="eyebrow"><span aria-hidden="true">✦</span> <?= h((string) $settings['site_name']) ?> · 心意收件站</p>
                <h1 id="home-heading">在这里，<em>留下一句话。</em></h1>
                <p class="hero-copy"><?= nl2br(h((string) $settings['site_description'])) ?></p>
                <div class="hero-tags" aria-hidden="true"><span>♡ 认真接收每一份心意</span><span>✧ 今天也要开心呀</span></div>
            </div>
            <div class="hero-art">
                <span class="art-sticker sticker-hello" aria-hidden="true">你好呀！<span>ฅ՞•ﻌ•՞ฅ</span></span>
                <svg class="mail-mascot" viewBox="0 0 360 300" fill="none" aria-hidden="true" focusable="false">
                    <ellipse cx="181" cy="161" rx="126" ry="120" fill="#fff" fill-opacity=".64"/>
                    <circle cx="181" cy="157" r="108" stroke="#fff" stroke-width="2" stroke-dasharray="4 8"/>
                    <ellipse cx="185" cy="269" rx="93" ry="12" fill="#ed9bbd" fill-opacity=".24"/>
                    <g stroke="#ad5978" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M112 121C58 118 79 204 59 222c23 11 56 1 63-30Z" fill="#f7a6c6"/>
                        <path d="M245 121c54-3 33 83 53 101-23 11-56 1-63-30Z" fill="#f7a6c6"/>
                        <path d="M94 142c-2 24 11 41-8 67m181-67c2 24-11 41 8 67" stroke="#e283ab"/>
                        <path d="m112 89 1-45c1-7 7-9 12-3l29 33m50 0 30-33c5-6 11-4 12 3l2 45" fill="#ffe8f1"/>
                        <path d="m122 75 1-21 15 19m81 0 16-19 1 21" fill="#f69ab9" stroke="none"/>
                        <path d="M104 131c-5-43 19-67 76-67s82 24 77 67l-7 49H112Z" fill="#ffb8d3"/>
                        <path d="M126 194c-14 18-21 45-20 70h150c1-27-6-51-21-70Z" fill="#fff4f8"/>
                        <path d="m145 188 35 17 34-17-9 35h-49Z" fill="#ffd0e2"/>
                        <path d="m161 208 19 10 18-10-4 23h-28Z" fill="#ee6da3"/>
                        <path d="m164 230-7 25 23-12 22 12-8-25" fill="#f38ab7"/>
                        <ellipse cx="113" cy="144" rx="12" ry="15" fill="#fff0e9"/>
                        <ellipse cx="247" cy="144" rx="12" ry="15" fill="#fff0e9"/>
                        <path d="M116 118c-2 15-2 30 1 41 7 29 31 44 63 44s56-15 63-44c3-11 3-26 1-41Z" fill="#fff3ed"/>
                        <path d="M108 127c3-48 30-63 72-63 42 0 69 15 72 63-18-2-35-12-45-28 1 16-6 27-11 32l-26-32c-7 17-22 29-38 33l3-20c-7 10-17 14-27 15Z" fill="#ffc1d9"/>
                        <path d="M148 78c-11 5-21 15-25 28m79-29c14 5 25 13 31 25" stroke="#ffe9f1" stroke-width="5"/>
                        <path d="m130 143 13-3m74 0 13 3" stroke="#82445f"/>
                        <ellipse cx="146" cy="151" rx="9.5" ry="15" fill="#9f5478" stroke="none"/>
                        <ellipse cx="214" cy="151" rx="9.5" ry="15" fill="#9f5478" stroke="none"/>
                        <ellipse cx="147" cy="158" rx="6" ry="6.5" fill="#ee92b9" stroke="none"/>
                        <ellipse cx="213" cy="158" rx="6" ry="6.5" fill="#ee92b9" stroke="none"/>
                        <circle cx="143" cy="145" r="3.5" fill="white" stroke="none"/>
                        <circle cx="211" cy="145" r="3.5" fill="white" stroke="none"/>
                        <circle cx="150" cy="153" r="1.8" fill="white" stroke="none"/>
                        <circle cx="218" cy="153" r="1.8" fill="white" stroke="none"/>
                        <ellipse cx="129" cy="171" rx="11" ry="6" fill="#f7abc1" fill-opacity=".6" stroke="none"/>
                        <ellipse cx="231" cy="171" rx="11" ry="6" fill="#f7abc1" fill-opacity=".6" stroke="none"/>
                        <path d="M173 175q7 8 14 0" stroke="#ba7285"/>
                        <path d="m107 117-16-8 2 18 14-3 13 7 1-19Z" fill="#ef6da5"/>
                        <path d="m252 117 16-8-2 18-14-3-13 7-1-19Z" fill="#ef6da5"/>
                        <path d="M129 216h102v60H129Z" fill="#fff7f4"/>
                        <path d="m130 217 50 33 50-33m-100 57 34-28m66 28-34-28" stroke="#ed9fb9"/>
                        <path d="M180 237c-12-14-23 5 0 18 23-13 12-32 0-18Z" fill="#ef76a8" stroke="none"/>
                        <ellipse cx="127" cy="238" rx="11" ry="14" fill="#fff0e9" transform="rotate(-15 127 238)"/>
                        <ellipse cx="233" cy="238" rx="11" ry="14" fill="#fff0e9" transform="rotate(15 233 238)"/>
                    </g>
                    <g fill="#f48fb8">
                        <path d="m60 89 4 11 11 4-11 4-4 11-4-11-11-4 11-4Z"/>
                        <path d="m296 147 3 8 8 3-8 3-3 8-3-8-8-3 8-3Z"/>
                        <path d="M284 70c-12-14-22 5 0 17 22-12 12-31 0-17Z"/>
                    </g>
                    <g fill="#fff">
                        <circle cx="77" cy="158" r="4"/><circle cx="280" cy="114" r="5"/>
                        <path d="m311 212 4 9 9 4-9 4-4 9-4-9-9-4 9-4Z"/>
                    </g>
                </svg>
                <div class="hero-aside">
                    <span class="stat-heart" aria-hidden="true">♡</span>
                    <span><strong><?= h((int) ($data['total'] ?? 0)) ?></strong> 份心意，已被珍藏</span>
                </div>
                <span class="art-sticker sticker-note" aria-hidden="true">You've got a little love ♡</span>
            </div>
        </section>
        <?php render_notices($data); ?>
        <div class="home-grid reveal reveal-delay">
            <aside class="compose-panel" aria-labelledby="compose-title">
                <p class="section-kicker">SEND A LITTLE LOVE</p>
                <h2 id="compose-title">投递一份心意 <span aria-hidden="true">♡</span></h2>
                <p class="panel-description">不必长篇大论，真诚就很好。</p>
                <form action="<?= h(app_url('/submit.php')) ?>" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="source" value="web">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <div class="field">
                        <label for="guest-name">怎么称呼你</label>
                        <input id="guest-name" name="name" type="text" maxlength="40" autocomplete="nickname" placeholder="留下你的昵称" value="<?= h((string) ($values['name'] ?? '')) ?>" required>
                    </div>
                    <div class="field">
                        <div class="label-row">
                            <label for="guest-message">想说的话</label>
                            <span class="counter" data-counter-for="guest-message" hidden>0 / 3000</span>
                        </div>
                        <textarea id="guest-message" name="message" maxlength="3000" placeholder="此刻，有什么想分享的？" required><?= h((string) ($values['message'] ?? '')) ?></textarea>
                    </div>
                    <div class="field">
                        <label for="guest-image">附上一张图片 <span class="optional">可选</span></label>
                        <input id="guest-image" name="image" type="file" accept="image/jpeg,image/png,image/gif,image/webp,image/bmp" aria-describedby="image-hint" data-image-input>
                        <p class="field-hint" id="image-hint">JPEG、PNG、GIF、WebP 或 BMP，最大 5 MB。</p>
                        <p class="field-error" data-image-error hidden></p>
                        <figure class="upload-preview" data-image-preview hidden><img alt="已选图片预览"></figure>
                    </div>
                    <div class="honeypot" aria-hidden="true">
                        <label for="guest-website">网站</label>
                        <input id="guest-website" name="website" type="text" tabindex="-1" autocomplete="off">
                    </div>
                    <button class="button button-wide" type="submit">投递留言 <span aria-hidden="true">↗</span></button>
                    <p class="form-footnote"><?= !empty($settings['moderation_required']) ? '留言审核后公开' : '留言提交后直接公开' ?>，请勿填写敏感个人信息。</p>
                </form>
                <div class="compose-bottom" aria-hidden="true">✧ 你的每一句话，都值得被听见 ✧</div>
            </aside>
            <section aria-labelledby="board-title">
                <div class="board-head">
                    <h2 id="board-title"><span aria-hidden="true">✿</span> 大家的留言</h2>
                    <span>按时间从新到旧</span>
                </div>
                <?php if ($messages === []): ?>
                    <div class="empty-state">
                        <span class="empty-symbol" aria-hidden="true">♡</span>
                        <h3>第一句话，等你来写</h3>
                        <p>这里还没有公开的留言。写下你的问候，<?= !empty($settings['moderation_required']) ? '审核通过后就会出现在这里' : '提交后就会出现在这里' ?>。</p>
                    </div>
                <?php else: ?>
                    <div class="message-list">
                        <?php foreach ($messages as $message): ?>
                            <article class="message-card">
                                <div class="message-top">
                                    <?php render_message_author($message); ?>
                                    <span class="message-number">#<?= h((int) $message['id']) ?></span>
                                </div>
                                <?php render_message_body($message); ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php render_pagination((int) ($data['page'] ?? 1), (int) ($data['pages'] ?? 1), '/'); ?>
            </section>
        </div>
        <?php
    }, ['site_name' => (string) ($data['site_name'] ?? '在线留言'), 'active' => 'home']);
}

function render_login(array $data): void
{
    $values = is_array($data['values'] ?? null) ? $data['values'] : [];
    render_page('管理员登录', static function () use ($data, $values): void {
        ?>
        <div class="auth-wrap">
            <section class="auth-intro reveal">
                <p class="eyebrow">BEHIND THE WORDS</p>
                <h1>照看每一句话，<br>也回应每一份心意。</h1>
                <div class="auth-rule" aria-hidden="true"></div>
                <p>这里是留言板的管理空间。审核留言、回复访客，让每一次交流都被好好对待。</p>
            </section>
            <section class="auth-panel reveal reveal-delay" aria-labelledby="login-heading">
                <p class="section-kicker">WELCOME BACK</p>
                <h2 class="panel-title" id="login-heading">欢迎回来</h2>
                <p class="panel-description">请使用管理员账号登录。</p>
                <?php render_notices($data); ?>
                <form action="<?= h(app_url('/admin/index.php')) ?>" method="post">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <div class="field">
                        <label for="login-username">管理员账号</label>
                        <input id="login-username" name="username" type="text" autocomplete="username" value="<?= h((string) ($values['username'] ?? '')) ?>" placeholder="输入管理员账号" required>
                    </div>
                    <div class="field">
                        <label for="login-password">密码</label>
                        <input id="login-password" name="password" type="password" autocomplete="current-password" placeholder="输入登录密码" required>
                    </div>
                    <button class="button button-wide" type="submit">登录管理后台 <span aria-hidden="true">→</span></button>
                    <p class="form-footnote"><a href="<?= h(app_url('/')) ?>">返回留言墙</a></p>
                </form>
            </section>
        </div>
        <?php
    }, ['site_name' => (string) ($data['site_name'] ?? '在线留言'), 'active' => 'login']);
}

function render_admin(array $data): void
{
    $settings = site_settings();
    $settingsValues = is_array($data['settings_values'] ?? null) ? $data['settings_values'] : $settings;
    $logoUrl = site_logo_url($settings);
    $messages = is_array($data['messages'] ?? null) ? $data['messages'] : [];
    $status = (string) ($data['status'] ?? 'all');
    $query = (string) ($data['q'] ?? '');
    $stats = is_array($data['stats'] ?? null) ? $data['stats'] : [];
    $labels = ['all' => '全部', 'pending' => '待审核', 'approved' => '已公开', 'hidden' => '已隐藏'];
    render_page('留言管理', static function () use ($data, $messages, $status, $query, $stats, $labels, $settings, $settingsValues, $logoUrl): void {
        ?>
        <header class="page-heading reveal">
            <div>
                <p class="eyebrow">THE EDITOR'S DESK</p>
                <h1>照看留言</h1>
                <p>你好，<?= h((string) ($data['admin_name'] ?? '管理员')) ?>。每一份心意，都值得认真回应。</p>
            </div>
            <a class="button button-secondary button-small" href="<?= h(app_url('/')) ?>">查看前台 <span aria-hidden="true">↗</span></a>
        </header>
        <div class="admin-content reveal reveal-delay">
            <?php render_notices($data); ?>
            <details class="site-settings" id="site-settings"<?= get_string('settings') === '1' || isset($data['settings_values']) ? ' open' : '' ?>>
                <summary>网站设置 <span><?= !empty($settings['moderation_required']) ? '先审后公开' : '提交即公开' ?></span></summary>
                <form class="site-settings-form" action="<?= h(app_url('/admin/index.php?settings=1')) ?>" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="settings_save">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <div class="field-grid">
                        <div class="field">
                            <label for="site-name">网站标题</label>
                            <input id="site-name" name="site_name" type="text" maxlength="60" value="<?= h((string) ($settingsValues['site_name'] ?? '')) ?>" required>
                        </div>
                        <div class="field">
                            <label for="footer-text">页脚文字</label>
                            <input id="footer-text" name="footer_text" type="text" maxlength="120" value="<?= h((string) ($settingsValues['footer_text'] ?? '')) ?>">
                        </div>
                        <div class="field field-full">
                            <label for="site-description">网站介绍</label>
                            <textarea id="site-description" name="site_description" maxlength="200" rows="3" aria-describedby="site-description-hint"><?= h((string) ($settingsValues['site_description'] ?? '')) ?></textarea>
                            <p class="field-hint" id="site-description-hint">显示在首页，也用于页面描述；最多 200 字，支持换行。</p>
                        </div>
                        <div class="field field-full">
                            <label for="site-logo">网站 LOGO <span class="optional">可选</span></label>
                            <?php if ($logoUrl !== null): ?>
                                <img class="current-logo" src="<?= h($logoUrl) ?>" alt="当前网站 LOGO">
                            <?php endif; ?>
                            <input id="site-logo" name="image" type="file" accept="image/jpeg,image/png,image/gif,image/webp,image/bmp" aria-describedby="site-logo-hint" data-image-input>
                            <p class="field-hint" id="site-logo-hint">用于页眉及浏览器图标。支持 JPG、PNG、GIF、WebP、BMP，最大 5 MB；动图仅保留首帧。</p>
                            <p class="field-error" data-image-error hidden></p>
                            <figure class="upload-preview" data-image-preview hidden><img alt="新 LOGO 预览"></figure>
                            <?php if ($logoUrl !== null): ?>
                                <label class="checkbox-label logo-reset"><input name="remove_logo" type="checkbox" value="1"<?= !empty($settingsValues['remove_logo']) ? ' checked' : '' ?>>移除自定义 LOGO，恢复默认图标</label>
                            <?php endif; ?>
                        </div>
                        <div class="field field-full">
                            <label class="checkbox-label" for="moderation-required"><input id="moderation-required" name="moderation_required" type="checkbox" value="1"<?= !empty($settingsValues['moderation_required']) ? ' checked' : '' ?> aria-describedby="moderation-hint">开启留言审核</label>
                            <p class="field-hint" id="moderation-hint">默认关闭。关闭时新留言直接公开；开启后需管理员审核。</p>
                        </div>
                    </div>
                    <button class="button button-small" type="submit">保存网站设置</button>
                </form>
            </details>
            <details class="background-settings" id="background-settings"<?= get_string('background') === '1' ? ' open' : '' ?>>
                <summary>背景设置 <span><?= is_file(BACKGROUND_FILE) ? '自定义背景' : '默认 5 张 · 随机轮换' ?></span></summary>
                <div class="background-gallery">
                    <?php foreach (background_urls() as $index => $url): ?>
                        <img src="<?= h($url) ?>" alt="背景图片 <?= h($index + 1) ?>" loading="lazy">
                    <?php endforeach; ?>
                </div>
                <form class="background-form" action="<?= h(app_url('/admin/index.php?background=1')) ?>" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="background_upload">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <div class="field">
                        <label for="background-image">更换背景图片</label>
                        <input id="background-image" name="image" type="file" accept="image/jpeg,image/png,image/gif,image/webp,image/bmp" aria-describedby="background-hint" data-image-input required>
                        <p class="field-hint" id="background-hint">上传后使用这张图片替代默认轮换。支持 JPG、PNG、GIF、WebP、BMP，最大 5 MB；动图仅保留首帧。</p>
                        <p class="field-error" data-image-error hidden></p>
                        <figure class="upload-preview" data-image-preview hidden><img alt="新背景预览"></figure>
                    </div>
                    <button class="button button-small" type="submit">保存背景</button>
                </form>
                <?php if (is_file(BACKGROUND_FILE)): ?>
                    <form class="background-reset" action="<?= h(app_url('/admin/index.php?background=1')) ?>" method="post">
                        <input type="hidden" name="action" value="background_reset">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <button class="button button-secondary button-small" type="submit">恢复默认轮换</button>
                    </form>
                <?php endif; ?>
            </details>
            <div class="admin-toolbar">
                <nav class="filter-tabs" aria-label="留言状态">
                    <?php foreach ($labels as $value => $label): ?>
                        <a href="<?= h(app_url('/admin/index.php?' . http_build_query(['status' => $value, 'q' => $query]))) ?>"<?= $status === $value ? ' aria-current="page"' : '' ?>>
                            <?= h($label) ?>
                            <span class="filter-count"><?= h($value === 'all' ? array_sum(array_map('intval', $stats)) : (int) ($stats[$value] ?? 0)) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <form class="search-form" action="<?= h(app_url('/admin/index.php')) ?>" method="get" role="search">
                    <input type="hidden" name="status" value="<?= h($status) ?>">
                    <label class="sr-only" for="admin-search">搜索昵称或留言内容</label>
                    <input id="admin-search" name="q" type="search" value="<?= h($query) ?>" placeholder="搜索昵称或留言内容">
                    <button class="button button-small" type="submit">搜索</button>
                </form>
            </div>
            <div class="board-head">
                <h2><?= h($labels[$status] ?? '全部') ?>留言</h2>
                <span>共 <?= h((int) ($data['total'] ?? 0)) ?> 条<?= $query !== '' ? '匹配结果' : '记录' ?></span>
            </div>
            <?php if ($messages === []): ?>
                <div class="empty-state">
                    <span class="empty-symbol" aria-hidden="true">静</span>
                    <h3><?= $query !== '' ? '暂时没有找到' : '这里已整理妥当' ?></h3>
                    <p><?= $query !== '' ? '试试其他关键词，或切换留言状态。' : (!empty($settings['moderation_required']) ? '当前状态下没有留言。新留言会先进入待审核列表。' : '当前状态下没有留言。新留言提交后会直接公开。') ?></p>
                </div>
            <?php else: ?>
                <form class="bulk-actions" id="bulk-delete" action="<?= h(app_url('/admin/index.php')) ?>" method="post" data-confirm="确认永久删除选中的留言及其图片？此操作无法撤销。">
                    <input type="hidden" name="action" value="bulk_delete">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <label class="checkbox-label" hidden><input type="checkbox" data-select-all>全选当前页</label>
                    <span class="selection-count" aria-live="polite" hidden>已选 <strong data-selected-count>0</strong> 条</span>
                    <button class="button button-danger button-small" type="submit" data-bulk-delete>删除选中留言</button>
                </form>
                <div class="message-list">
                    <?php foreach ($messages as $message): ?>
                        <?php
                        $id = (int) $message['id'];
                        $itemStatus = (string) ($message['status'] ?? 'pending');
                        $badgeClass = match ($itemStatus) {
                            'approved' => '',
                            'hidden' => ' badge-hidden',
                            default => ' badge-pending',
                        };
                        ?>
                        <article class="message-card admin-card" id="message-<?= h($id) ?>">
                            <div class="message-top">
                                <div class="message-selection">
                                    <label class="message-select"><input type="checkbox" name="ids[]" value="<?= h($id) ?>" form="bulk-delete" data-message-select><span class="sr-only">选择第 <?= h($id) ?> 条留言</span></label>
                                    <?php render_message_author($message); ?>
                                </div>
                                <div class="message-badges">
                                    <span class="badge<?= h($badgeClass) ?>"><?= h($labels[$itemStatus] ?? '待审核') ?></span>
                                    <span class="message-meta">#<?= h($id) ?> · <?= ($message['source'] ?? 'web') === 'desktop' ? '桌面端' : '网页端' ?></span>
                                </div>
                            </div>
                            <?php render_message_body($message); ?>
                            <div class="admin-actions">
                                <div class="action-group">
                                    <?php if ($itemStatus !== 'approved'): ?>
                                        <form action="<?= h(app_url('/admin/index.php')) ?>" method="post">
                                            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="status">
                                            <input type="hidden" name="id" value="<?= h($id) ?>">
                                            <input type="hidden" name="status" value="approved">
                                            <button class="button button-small" type="submit">批准公开</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($itemStatus !== 'hidden'): ?>
                                        <form action="<?= h(app_url('/admin/index.php')) ?>" method="post">
                                            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="status">
                                            <input type="hidden" name="id" value="<?= h($id) ?>">
                                            <input type="hidden" name="status" value="hidden">
                                            <button class="button button-secondary button-small" type="submit">隐藏留言</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <form action="<?= h(app_url('/admin/index.php')) ?>" method="post" data-confirm="确认永久删除这条留言及其图片？此操作无法撤销。">
                                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= h($id) ?>">
                                    <button class="button button-danger button-small" type="submit">永久删除</button>
                                </form>
                            </div>
                            <details class="admin-reply">
                                <summary><?= (string) ($message['reply'] ?? '') !== '' ? '编辑站长回复' : '写一条回复' ?></summary>
                                <form class="reply-form" action="<?= h(app_url('/admin/index.php')) ?>" method="post">
                                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="reply">
                                    <input type="hidden" name="id" value="<?= h($id) ?>">
                                    <div class="field">
                                        <div class="label-row">
                                            <label for="reply-<?= h($id) ?>">回复 <?= h((string) ($message['name'] ?? '访客')) ?></label>
                                            <span class="counter" data-counter-for="reply-<?= h($id) ?>" hidden>0 / 3000</span>
                                        </div>
                                        <textarea id="reply-<?= h($id) ?>" name="reply" maxlength="3000" placeholder="写下你的回应……"><?= h((string) ($message['reply'] ?? '')) ?></textarea>
                                    </div>
                                    <button class="button button-small" type="submit">保存回复</button>
                                </form>
                            </details>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php render_pagination((int) ($data['page'] ?? 1), (int) ($data['pages'] ?? 1), '/admin/index.php', ['status' => $status, 'q' => $query]); ?>
        </div>
        <?php
    }, ['site_name' => (string) ($data['site_name'] ?? '在线留言'), 'active' => 'admin', 'admin' => true]);
}

function render_install(array $data): void
{
    $step = (string) ($data['step'] ?? 'check');
    $values = is_array($data['values'] ?? null) ? $data['values'] : [];
    $checks = is_array($data['checks'] ?? null) ? $data['checks'] : [];
    render_page('安装向导', static function () use ($data, $step, $values, $checks): void {
        ?>
        <div class="install-wrap reveal">
            <header class="install-heading">
                <p class="eyebrow">MAKE ROOM FOR CONVERSATION</p>
                <h1>为交流，留一处地方。</h1>
                <p>完成简单配置，开启你的留言板。安装过程中不会自动创建数据库。</p>
            </header>
            <?php if ($step !== 'locked'): ?>
                <?php $currentStep = match ($step) { 'setup' => 2, 'done' => 3, default => 1 }; ?>
                <ol class="steps" aria-label="安装进度">
                    <?php foreach ([1 => '环境检查', 2 => '信息配置', 3 => '安装完成'] as $number => $label): ?>
                        <li class="<?= $number === $currentStep ? 'is-current' : ($number < $currentStep ? 'is-done' : '') ?>"<?= $number === $currentStep ? ' aria-current="step"' : '' ?>>
                            <span class="step-number"><?= h($number) ?></span><?= h($label) ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            <?php render_notices($data); ?>
            <section class="install-panel">
                <?php if ($step === 'locked'): ?>
                    <div class="complete-state">
                        <div class="complete-mark" aria-hidden="true">✓</div>
                        <h2>留言板已经准备好了</h2>
                        <p>此站点已完成安装，不能重复安装。</p>
                        <div class="complete-actions">
                            <a class="button" href="<?= h(app_url('/admin/index.php')) ?>">进入管理后台</a>
                            <a class="button button-secondary" href="<?= h(app_url('/')) ?>">查看留言墙</a>
                        </div>
                    </div>
                <?php elseif ($step === 'done'): ?>
                    <div class="complete-state">
                        <div class="complete-mark" aria-hidden="true">✓</div>
                        <p class="section-kicker">READY FOR THE FIRST HELLO</p>
                        <h2>一切就绪，等一句问候。</h2>
                        <p>留言板已安装完成，现在可以登录审核和回复留言。</p>
                        <?php $adminUser = (string) ($data['admin_user'] ?? $values['admin_user'] ?? ''); ?>
                        <?php if ($adminUser !== ''): ?><p>管理员账号：<strong><?= h($adminUser) ?></strong></p><?php endif; ?>
                        <div class="complete-actions">
                            <a class="button" href="<?= h(app_url('/admin/index.php')) ?>">登录管理后台 <span aria-hidden="true">→</span></a>
                            <a class="button button-secondary" href="<?= h(app_url('/')) ?>">查看留言墙</a>
                        </div>
                    </div>
                <?php elseif ($step === 'setup'): ?>
                    <h2 class="panel-title">配置你的留言板</h2>
                    <p class="panel-description">请先在宝塔创建 MySQL 数据库，再填写数据库信息。</p>
                    <form action="<?= h(app_url('/install/index.php')) ?>" method="post">
                        <input type="hidden" name="action" value="install">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <fieldset class="form-section">
                            <legend>01 / 数据库连接</legend>
                            <div class="field-grid">
                                <div class="field">
                                    <label for="db-host">数据库地址</label>
                                    <input id="db-host" name="db_host" value="<?= h((string) ($values['db_host'] ?? '127.0.0.1')) ?>" autocomplete="off" required>
                                </div>
                                <div class="field">
                                    <label for="db-port">端口</label>
                                    <input id="db-port" name="db_port" type="number" min="1" max="65535" value="<?= h((string) ($values['db_port'] ?? '3306')) ?>" required>
                                </div>
                                <div class="field">
                                    <label for="db-name">数据库名称</label>
                                    <input id="db-name" name="db_name" value="<?= h((string) ($values['db_name'] ?? '')) ?>" autocomplete="off" required>
                                </div>
                                <div class="field">
                                    <label for="db-user">数据库用户名</label>
                                    <input id="db-user" name="db_user" value="<?= h((string) ($values['db_user'] ?? '')) ?>" autocomplete="off" required>
                                </div>
                                <div class="field">
                                    <label for="db-password">数据库密码</label>
                                    <input id="db-password" name="db_password" type="password" autocomplete="new-password">
                                </div>
                                <div class="field">
                                    <label for="table-prefix">数据表前缀</label>
                                    <input id="table-prefix" name="table_prefix" value="<?= h((string) ($values['table_prefix'] ?? 'mox_')) ?>" autocomplete="off" required>
                                </div>
                            </div>
                        </fieldset>
                        <fieldset class="form-section">
                            <legend>02 / 站点与管理员</legend>
                            <div class="field-grid">
                                <div class="field">
                                    <label for="site-name">站点名称</label>
                                    <input id="site-name" name="site_name" value="<?= h((string) ($values['site_name'] ?? '在线留言')) ?>" required>
                                </div>
                                <div class="field">
                                    <label for="admin-user">管理员账号</label>
                                    <input id="admin-user" name="admin_user" autocomplete="username" value="<?= h((string) ($values['admin_user'] ?? '')) ?>" required>
                                </div>
                                <div class="field">
                                    <label for="admin-password">管理员密码</label>
                                    <input id="admin-password" name="admin_password" type="password" minlength="12" autocomplete="new-password" aria-describedby="password-hint" required>
                                    <p class="field-hint" id="password-hint">至少 12 个字符，请使用独立且不易猜测的密码。</p>
                                </div>
                                <div class="field">
                                    <label for="admin-password-confirm">再次输入密码</label>
                                    <input id="admin-password-confirm" name="admin_password_confirm" type="password" minlength="12" autocomplete="new-password" required>
                                </div>
                            </div>
                        </fieldset>
                        <div class="install-actions">
                            <p>请妥善保存管理员账号和密码，完成后使用该账号登录管理后台。</p>
                            <button class="button" type="submit">完成安装 <span aria-hidden="true">→</span></button>
                        </div>
                    </form>
                <?php else: ?>
                    <h2 class="panel-title">先确认环境就绪</h2>
                    <p class="panel-description">检查运行环境，并验证你对服务器的管理权限。</p>
                    <details class="deployment-help">
                        <summary>宝塔部署检查</summary>
                        <ul>
                            <li>将压缩包解压到站点目录，运行目录指向解压根目录，无需伪静态规则。</li>
                            <li>选择 PHP 8.5，启用 PDO MySQL、mbstring、fileinfo、GD 扩展。</li>
                            <li>PHP 配置：<code>upload_max_filesize = 5M</code>、<code>post_max_size = 6M</code>、<code>memory_limit = 256M</code>。</li>
                            <li>Nginx 站点的 <code>server</code> 配置中设置 <code>client_max_body_size 6m;</code>。</li>
                            <li>自行填写数据库名、用户名和密码。</li>
                            <li>确保 <code>storage</code> 目录归站点 PHP 运行用户所有并可写，并为站点启用 HTTPS。</li>
                        </ul>
                        <p>根域站点可额外添加以下 Nginx 规则，阻止直接访问内部目录：</p>
                        <pre><code>location ~ ^/(?:app|storage)/ { return 404; }</code></pre>
                    </details>
                    <ul class="check-list">
                        <?php foreach ($checks as $check): ?>
                            <li class="check-item">
                                <div>
                                    <span class="check-label"><?= h((string) ($check['label'] ?? '环境检查')) ?></span>
                                    <?php if ((string) ($check['detail'] ?? '') !== ''): ?>
                                        <span class="check-detail"><?= h((string) $check['detail']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="check-status<?= empty($check['ok']) ? ' is-failed' : '' ?>"><?= empty($check['ok']) ? '未通过' : '已就绪' ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <form action="<?= h(app_url('/install/index.php')) ?>" method="post">
                        <input type="hidden" name="action" value="configure">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <div class="field">
                            <label for="install-key">安装口令</label>
                            <input id="install-key" name="install_key" type="password" autocomplete="off" aria-describedby="install-key-hint" required>
                            <p class="field-hint" id="install-key-hint">请使用宝塔文件管理，打开站点目录中的 <code>storage/install-key.php</code>，复制其中的口令字符串。不要公开或转发此文件。</p>
                        </div>
                        <div class="install-actions">
                            <p>环境检查未通过时，请先调整服务器配置，再刷新此页面。</p>
                            <button class="button" type="submit">验证并继续 <span aria-hidden="true">→</span></button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }, ['site_name' => (string) ($data['site_name'] ?? '在线留言')]);
}

function render_error(string $title, string $message, int $status): void
{
    http_response_code($status);
    render_page($title, static function () use ($title, $message, $status): void {
        ?>
        <section class="error-wrap reveal">
            <p class="error-code" aria-hidden="true"><?= h($status) ?></p>
            <h1><?= h($title) ?></h1>
            <p class="error-message"><?= h($message) ?></p>
            <a class="button" href="<?= h(app_url('/')) ?>">返回留言墙 <span aria-hidden="true">→</span></a>
        </section>
        <?php
    }, ['settings_disabled' => true]);
}
