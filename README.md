# MOX 在线留言板

一个基于 PHP 和 MySQL 的轻量留言板，支持网页留言及易语言等桌面客户端的在线反馈。内置管理后台和安装向导，上传源码后即可通过浏览器完成配置，无需 Composer、Node.js 构建或 Redis。

## 功能

- 游客可提交昵称、留言和一张可选图片，前台分页展示公开留言及管理员回复。
- 粉色二次元界面，内置 5 张随机切换背景，兼容桌面和手机；后台支持更换背景和恢复默认轮换。
- 后台支持搜索、状态筛选、公开或隐藏留言、回复、单条删除和批量删除；删除留言时一并删除附件。
- 可设置网站标题、介绍、页脚文字和 LOGO，支持上传新 LOGO 或恢复默认图标。
- 留言审核默认关闭，新留言直接公开；开启后需管理员审核。切换开关不会改变已有留言的状态。
- 安装时自行设置管理员账号和密码，安装完成后禁止重复安装。

## 运行环境

| 项目 | 要求 |
| --- | --- |
| PHP | 8.2 或更高版本，目标部署版本为 8.5 |
| PHP 扩展 | `pdo_mysql`、`mbstring`、`fileinfo`、`gd`、`session` |
| GD | 支持 JPEG、PNG、WebP |
| 数据库 | MySQL 5.7 或更高版本 |
| Web 服务 | Nginx + PHP-FPM，无需伪静态规则 |
| 目录权限 | `storage` 目录对 PHP 运行用户可写 |

PHP 配置至少满足：

```ini
upload_max_filesize = 5M
post_max_size = 6M
memory_limit = 256M
```

Nginx 站点的 `server` 配置中设置：

```nginx
client_max_body_size 6m;
```

MySQL 的 `max_allowed_packet` 需至少为 `8M`，建议设为 `16M`。安装向导会检查环境要求。

## 引导安装

1. 将源码上传或解压到网站目录，网站运行目录指向包含 `index.php` 的目录。
2. 配置 PHP 环境和目录权限，准备可连接的 MySQL 数据库及账号。
3. 访问 `/install/index.php`，检查运行环境。
4. 通过服务器文件管理打开自动生成的 `storage/install-key.php`，将其中的安装口令填写到向导中。
5. 在信息配置页自行填写数据库地址、端口、数据库名、用户名、密码和表前缀。
6. 填写站点名称，并设置管理员账号、密码及确认密码。账号为 3–32 位字母、数字、下划线或短横线；密码至少 12 个字符，最多 72 字节。
7. 完成安装后，访问 `/admin/index.php`，使用刚设置的管理员账号登录。

安装器只在已有数据库中创建带前缀的 `admins`、`messages`、`rate_limits` 三张表，不创建数据库或数据库用户。表前缀已被使用时会拒绝安装，不覆盖已有数据。

安装口令与管理员密码是两回事：前者用于验证服务器管理权限，后者由你在安装过程中设置，用于登录后台。

## 使用入口

| 地址 | 用途 |
| --- | --- |
| `/` | 留言墙和游客留言表单 |
| `/admin/index.php` | 管理员登录、留言管理、网站信息及背景设置 |
| `/install/index.php` | 首次安装向导，安装后锁定 |
| `/submit.php` | 留言提交接口 |

在后台「网站设置」中可修改网站信息、LOGO 和留言审核开关；「背景设置」用于更换背景图片或恢复默认轮换。

## 桌面客户端反馈接口

向 `/submit.php` 发送 UTF-8 编码的 `POST` 表单；携带图片时使用 `multipart/form-data`。

| 字段 | 说明 |
| --- | --- |
| `name` | 必填，昵称，1–40 个字符，不可换行 |
| `message` | 必填，留言，1–3000 个字符 |
| `image` | 可选图片文件，最大 5 MB，支持 JPEG、PNG、GIF、WebP、BMP |
| `source` | 桌面客户端填写 `desktop`，省略时也按桌面来源处理 |

示例：

```bash
curl "https://example.com/submit.php" \
  -F "source=desktop" \
  -F "name=访客" \
  -F "message=这是一条反馈" \
  -F "image=@screenshot.png"
```

无图片时省略 `image` 字段。图片单边不超过 6000 像素，总像素不超过 1200 万；上传后由 GD 重新编码并存入数据库，动图仅保留首帧。

成功时返回 HTTP 200 和 JSON：

```json
{"ok":true,"id":123,"status":"approved","message":"留言已发布，感谢你的分享。"}
```

开启审核后，成功响应的 `status` 为 `pending`，提示等待审核。失败响应的 `ok` 为 `false`，`message` 包含错误提示；重复提交返回 409，大小限制返回 413，输入校验失败返回 422，提交过于频繁返回 429。

每个 IP 在每 10 分钟固定窗口内最多进行 10 次提交尝试。网页端使用会话和 CSRF 校验，`source=web` 返回页面跳转，不适用于期望 JSON 响应的桌面客户端。

## 数据与配置

- 留言、留言图片、管理员账号及密码哈希保存在 MySQL 中。
- `storage/config.php` 保存安装配置和数据库连接信息。
- `storage/site-settings.php` 保存网站信息、审核开关和自定义 LOGO。
- `storage/background.php` 保存自定义背景；未设置时使用 `assets/backgrounds` 内的默认图片。

运行时配置、安装口令和本地部署压缩包已在 `.gitignore` 中排除。备份时需同时保留数据库和 `storage` 目录；不要公开其中的配置文件。
