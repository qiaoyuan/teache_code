# 成长档案：成绩记录与招生意向采集

Laravel 13 + PHP 8.4 + MySQL + Vue 3 / TypeScript。家长端 `/app/`，管理员后台 `/admin/`。后台使用 Element Plus；两端共享业务数据库，分别使用登录模型、认证 guard 和 Cookie。

## 本机运行

此项目已安装依赖、构建前端并完成 `shengxue360` 的迁移。管理员已创建，本机凭据在 Git 忽略文件 `.local-access.txt`。家长通过注册页创建账号，已填报数据保留。原 `parent-h5` 是旧原型，正式运行入口为 Laravel 的 `public/index.php`。

```sh
./scripts/php artisan serve --host=127.0.0.1 --port=8000
```

- 家长端：http://127.0.0.1:8000/app/
- 管理后台：http://127.0.0.1:8000/admin/

本机 `scripts/php` 使用已安装的 PHP 8.4 并隔离旧版本扩展配置；`scripts/node` 选择已有 Node 24，不改变系统全局运行环境。其他机器无这些路径时使用系统 PHP/Node。

修改前端后：

```sh
./scripts/node npm run build
```

开发时也可另开终端执行 `./scripts/node npm run dev`，使用 Vite 热更新。构建文件不纳入 Git，新环境需重新构建。

## 项目 skill 与自动同步

开发 skill 的仓库源文件为 `docs/skills/shengxue360-development/SKILL.md`，记录稳定业务规则、数据库约定和验证流程；`references/project-state.md` 由脚本生成最新结构、路由声明、锁定依赖和迁移清单。项目 `AGENTS.md` 要求修改前读取 skill。

```sh
# 初次安装到当前用户 Codex skill 目录，默认 ~/.codex/skills/
rtk proxy ./scripts/node npm run skill:install
# 手动同步仓库源文件与已安装的副本
rtk proxy ./scripts/node npm run skill:sync
# 测试同步机制（独立临时目录，不操作业务库）
rtk proxy ./scripts/node npm run skill:test
```

`.codex/hooks.json` 配置 Codex 的 PostToolUse（命令、补丁、编辑）和 Stop 事件。原生 hooks 需信任当前项目，并在 Codex CLI 的 `/hooks` 中审阅、信任新 hook；配置改变后需重新审阅。安装 skill 不等于 hook 已获信任，具体规则见 [Codex hooks 官方文档](https://learn.chatgpt.com/docs/hooks)。

启用后，相关工具操作完成及回合结束会检查源文件变化，有变化才刷新 skill。`npm run build` 在构建成功后直接执行同步，不依赖 Codex hook 信任或 npm postbuild 生命周期（本项目 `.npmrc` 禁用安装脚本）。外部编辑器单独保存不会触发 Codex 事件，可构建或手动同步。新需求要同步设计文档及 skill 手写规则；脚本仅维护生成摘要与结构，不从代码猜测业务决策。

同步使用源文件白名单，不读取 `.env`、账号凭据、业务数据库、备份、日志和聊天，不执行迁移或部署。安装登记、源文件哈希和短期锁放在已被 Git 忽略的 `storage/app/private/skill-sync/`，不含源代码和业务内容。无变化不改写文件，避免 hook 自触发循环。

## 新环境安装

需要 PHP 8.4+（PDO MySQL、mbstring、intl、DOM、XMLReader/Writer、ZIP 等扩展）、Composer、Node 22.12+、MySQL 8.0+。依赖精确版本记录在两份 lock 文件中。本机验证版本为 PHP 8.4.5、Node 24.14.1、MySQL 9.2.0；MySQL 8.0 的生产部署尚未实机验证。

```sh
composer install
cp .env.example .env
# 配置 APP_URL、数据库、ADMIN_USERNAME 和 ADMIN_PASSWORD
php artisan key:generate
php artisan migrate --force
php artisan admin:init --no-interaction
npm ci --ignore-scripts
npm run build
```

用于本地 HTTP 开发的新环境，把 APP_ENV 改为 local、APP_DEBUG 改为 true、SESSION_SECURE_COOKIE 改为 false；生产 HTTPS 保持示例中的生产设置。

不要覆盖已有部署的 APP_KEY。`admin:init` 重复执行不会修改既有账号；新管理员可用不同用户名创建。密码至少 8 位、包含字母和数字，最多 64 字符且 UTF-8 不超过 72 字节。初始化密码也可通过交互式 `php artisan admin:init` 输入。

本地数据库参数保存在 `.env`，按用户提供的 `127.0.0.1:3306 / shengxue360 / root / 空密码` 使用。上线时填写生产连接和凭据。示例文件不含本机管理员密码。

## 代码布局

```text
app/Http/Controllers/ParentPortal   家长 PHP 控制器
app/Http/Controllers/Admin          后台 PHP 控制器
app/Http/Requests/ParentPortal      家长表单校验
app/Http/Requests/Admin             后台重置校验
app/Http/Middleware                 两端 Cookie 配置、登录与强制改密
app/Services/ParentPortal           归属检查、考试与意向事务
app/Services/Admin                  查询筛选、SQL 汇总、重置和 Excel
app/Services/Shared                 文本规范化、单科分析
app/Models / app/Policies           数据模型与学生归属授权
resources/js/app                   手机优先的家长界面
resources/js/admin                 Element Plus 管理后台
resources/js/shared                API、类型与折线图
routes/web.php                     页面与使用 session/CSRF 的 JSON 接口
```

业务接口均位于 `/api/v1/app` 和 `/api/v1/admin`，无管理员业务编辑接口。API 未登录返回 401；现存记录跨家长访问返回 403；不存在、软删除及祖先删除返回 404。考试更新需携带 version，过期返回 409 / VERSION_CONFLICT。

家长和后台分别使用 `growth_parent_session`、`growth_admin_session`。任一端登出不会影响另一端。会话记录增加 portal（parent/admin），按角色和 user_id 定位。管理员重置家长密码会仅删除该家长旧会话并增加 auth_version，要求家长下次登录改密；家长和管理员数字 ID 相同也不会互相影响。POST、PUT、DELETE 使用 CSRF；令牌过期时前端取新令牌，仅重试被 CSRF 拒绝的一次请求。

导出使用 OpenSpout 生成真正的 XLSX，按 500 条批次写入。全量导出有学生、科目成绩、学校意向三张表；筛选导出忽略分页。文本显式写为字符串，手机号、记录 ID 和公式开头文本不会被作为数字或公式执行。

## 主键与已有数据库升级

parents、administrators、students、exams、exam_subjects、intentions、audit_logs 七张业务表的 `id` 均为 `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`；id 放在表的第一个字段；created_at、updated_at、deleted_at（存在时）使用 DATETIME，按 UTC 写入并由 Laravel 维护。业务外键使用同类型整数，API 返回数字 ID。手机号仍为字符串。模型使用 Eloquent 默认自增主键，不再生成 UUID。

Laravel 的 sessions.id 是随机会话标识，cache/cache_locks.key 是缓存键，job_batches.id 是框架批次标识，保持框架默认；jobs、failed_jobs、migrations 已使用自增主键。sessions.user_id 已改成整数，portal 区分家长和管理员。

新安装直接执行迁移。已有 UUID 数据库升级必须先备份并进入维护模式，新增迁移会映射全部主键与外键，包含软删除记录和审计目标；不能直接把 UUID 转成数字：

```sh
# 先按“数据库备份与恢复”章节备份
php artisan down --retry=30
php artisan migrate --force
php artisan up
```

升级后旧会话失效，两端重新登录，账号与密码保持原值。旧 UUID 记录链接需改用新 ID。该迁移不支持回滚到原 UUID；若迁移中途失败，保持维护模式，从升级前备份完整恢复数据库后重试。本机升级前备份位于忽略版本控制的 `storage/app/private/backups/before-integer-ids-20261012.sql`，文件权限 600。

已有库另有迁移 `2026_10_12_000005_put_id_first_and_use_datetime`，将 id 调到首列并把业务时间字段转换为 DATETIME。转换时显式使用 UTC，保留原始时间、NULL 和软删除状态。升级前备份位于 `storage/app/private/backups/before-datetime-20261012.sql`。

## 测试

```sh
./scripts/php artisan test
./scripts/node npm run build
./scripts/php vendor/bin/pint --test
```

默认测试使用内存 SQLite。测试基类拒绝操作正式数据库。可使用专用 MySQL 库验证迁移与约束，库名必须以 `shengxue360_test_` 开头：

```sh
mysql -h 127.0.0.1 -u root -e 'CREATE DATABASE shengxue360_test_local CHARACTER SET utf8mb4'
DB_CONNECTION=mysql DB_DATABASE=shengxue360_test_local ./scripts/php artisan test
```

PHPUnit 的 RefreshDatabase 会重建测试库，请只给它新建的专用库。真实 Cookie/CSRF 与浏览器联调使用另一个临时库，不与 PHPUnit 同时运行：

```sh
mysql -h 127.0.0.1 -u root -e 'CREATE DATABASE shengxue360_test_preview CHARACTER SET utf8mb4'
DB_DATABASE=shengxue360_test_preview ./scripts/php artisan migrate --force
DB_DATABASE=shengxue360_test_preview ./scripts/php scripts/preview-seed.php
DB_DATABASE=shengxue360_test_preview APP_URL=http://127.0.0.1:8001 ./scripts/php artisan serve --host=127.0.0.1 --port=8001
# 另一个终端运行一次：
python3 scripts/http-smoke.py --url http://127.0.0.1:8001 --isolated-test
```

preview-seed 拒绝正式库；HTTP 脚本在这个空测试库中创建固定测试家长和虚构学生，验证两端 Cookie、越权、CSRF、重置改密、Excel 和审计。脚本完成后测试家长为 `13800000001 / PreviewParent123`、测试管理员为 `preview_admin / PreviewAdmin123`，仅用于专用测试库。再次运行前重建该测试库。

## LNMP 部署

1. 配置 PHP 8.4-FPM、生产 MySQL 和域名；Nginx 站点根目录必须指向项目 `public/`。
2. 使用 `deploy/nginx.conf.example`，按服务器修改根目录、server_name、FPM socket，并配置域名 HTTPS 证书。
3. `.env` 设置 `APP_ENV=production`、`APP_DEBUG=false`、正确的 HTTPS `APP_URL`、`SESSION_SECURE_COOKIE=true`、数据库账号、APP_KEY 与管理员初始化配置。
4. 执行 `composer install --no-dev --optimize-autoloader`、`npm ci --ignore-scripts`、`npm run build`、`php artisan migrate --force`、首次 `php artisan admin:init --no-interaction`。
5. 给 FPM 用户写入 `storage/` 和 `bootstrap/cache/` 的权限，随后执行 `php artisan config:cache`、`php artisan route:cache`、`php artisan view:cache`。
6. 检查 `/up`、两端登录、填报、跨账号禁止访问及 Excel 下载。

初始化命令在配置缓存后也读取 `config/growth.php`，修改环境配置后重新执行 config:cache。两端构建资源可由 Nginx 提供，接口交给 PHP-FPM，同源无需 CORS。首版同步生成 Excel，无额外队列进程。

当前本地服务只监听 127.0.0.1。生产域名、服务器与证书未部署；静态原型预览地址仍保持原型功能。

## 数据库备份与恢复

为运维账号配置 MySQL login-path，密码通过交互输入，避免出现在命令或历史中：

```sh
mysql_config_editor set --login-path=growth --host=127.0.0.1 --user=backup_user --password
mysqldump --login-path=growth --single-transaction --no-tablespaces --default-character-set=utf8mb4 shengxue360 > growth-backup.sql
```

运维将备份命令加入每天的计划任务，保存到受限目录，并轮换保留周期、异地副本。单独保存应用 APP_KEY 和部署配置；数据库备份并不包含这些文件。

恢复先在新建的专用库中进行，核对账号、学生、考试、科目、意向与审计数量，再安排正式切换，不覆盖线上库进行演练：

```sh
mysql --login-path=growth -e 'CREATE DATABASE shengxue360_restore_check CHARACTER SET utf8mb4'
mysql --login-path=growth shengxue360_restore_check < growth-backup.sql
```

本机已使用虚构测试数据进行一次备份导出和恢复检查；生产定时备份与生产恢复演练尚未执行。

需求及接口的完整规则见 `docs/开发设计文档.md`，验收证据见 `docs/验收记录.md`。
