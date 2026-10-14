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

`.codex/hooks.json` 配置 Codex 的 PostToolUse（所有成功工具调用）和 Stop 事件。原生 hooks 需信任当前项目，并在 Codex CLI 的 `/hooks` 中审阅、信任新 hook；配置改变后需重新审阅。安装 skill 不等于 hook 已获信任，具体规则见 [Codex hooks 官方文档](https://learn.chatgpt.com/docs/hooks)。

启用后，相关工具操作完成及回合结束会检查源文件变化，有变化才刷新 skill。`npm run build` 在构建成功后直接执行同步，不依赖 Codex hook 信任或 npm postbuild 生命周期（本项目 `.npmrc` 禁用安装脚本）。外部编辑器单独保存不会触发 Codex 事件，可构建或手动同步。每次修改代码、配置、文档或 hook，交付前都执行 `skill:sync`；业务行为、接口约定、开发或部署流程变化时，同时维护设计文档及 skill 正文。脚本仅维护生成摘要与结构，不从代码猜测业务决策。

同步使用源文件白名单，不读取 `.env`、账号凭据、业务数据库、备份、日志和聊天，不执行迁移或部署。安装登记、源文件哈希和短期锁放在已被 Git 忽略的 `storage/app/private/skill-sync/`，不含源代码和业务内容。无变化不改写文件，避免 hook 自触发循环。

生成快照以 `.` 表示仓库根目录，不记录执行机器的绝对路径。同一份源码在本机与线上构建后的快照应一致；安装副本的私有 `.project-binding.json` 仍记录实际路径以核对项目归属。旧版线上遗留的路径差异处理见 `deploy/README.md`。

## 注册协议

家长注册需主动同意《用户协议与隐私政策》，可在弹窗阅读或访问 `/app/agreement` 阅读完整页面。服务端拒绝未同意或旧版本注册，保存当前版本及 UTC 同意时间；原有账号记录保持 NULL。协议版本、最近更新日期与联系方式位于 `config/agreement.php`，正文位于 `resources/views/agreement.blade.php`。修改正文时同步更新版本。

本次新增迁移只给 parents 增加两个可空字段，已有环境执行 `./scripts/php artisan migrate --force`，不重建数据库。隐私联系电话为 18117988868，可在“我的”查看并联系申请注销或撤回授权；在线自动处理入口未实现。生产环境按下文部署章节启用 HTTPS。

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

导出使用 OpenSpout 生成真正的 XLSX，按 500 条批次写入。全量导出有学生、科目成绩、学校意向三张表；筛选导出忽略分页。all 全量工作簿的三张表均按注册家长手机号升序、学生姓名升序、记录 ID 升序排列，使相同手机号、相同姓名的记录连续；排序在数据库分批读取前完成。单类导出和后台列表保持原有排序。文本显式写为字符串，手机号、记录 ID 和公式开头文本不会被作为数字或公式执行。

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

当前宝塔采用 www 家长端、admin 后台：使用 [宝塔双域名配置](deploy/nginx.baota.conf.example)，细节见部署说明。旧单域名示例不与之同时加载。

完整首次安装、HTTPS、权限、更新步骤及排错见 [部署说明](deploy/README.md)。

- 正式 Nginx 配置：[deploy/nginx.conf.example](deploy/nginx.conf.example)。
- 无证书时的临时验证站点：[deploy/nginx.http-bootstrap.conf.example](deploy/nginx.http-bootstrap.conf.example)。
- 生产环境模板：[deploy/env.production.example](deploy/env.production.example)，首次安装复制为根目录 `.env` 并填入实际配置。

使用 PHP 8.4-FPM，站点根目录指向 public；家长 `/app/` 与后台 `/admin/` 统一走 Laravel。部署模板以 HTTPS 为前提，不覆盖本机开发 `.env`。更新时保留已有 APP_KEY 与数据库数据；Git 不包含 public/build，需要构建或上传对应产物。

当前材料未替用户部署生产服务器、申请证书或配置定时备份。

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

## V1.7 管理员账号管理

- 后台新增 `/admin/administrators` 页面，支持分页查看管理员、新增账号和修改自己或其他管理员密码。继续使用单一 admin 角色，所有管理员权限相同；本次不增加删除、禁用或角色分级。
- 新建账号为 3–64 位 ASCII 字母/数字/下划线/点/短横线，以字母或数字开头，统一转小写并防止重名。密码沿用 8–64 位、含字母和数字、UTF-8 不超过 72 字节规则，需确认密码。
- 新增和改密均验证操作者当前密码并限流；仅后台已登录用户可访问，家长无法使用。保存密码哈希，不返回明文或哈希。
- `administrators.auth_version` 为 unsigned integer，默认 0，以新增迁移保留现有账号及密码。改密递增版本并按 portal=admin、user_id 清除目标会话，拦截旧版本会话；修改自己密码后退出并重新登录，不影响同数字 ID 的家长会话。
- 审计记录操作者、目标账号、IP、时间；不记录密码。新增接口：GET/POST `/api/v1/admin/administrators`，POST `/api/v1/admin/administrators/{id}/password`。
- 升级需先执行 `php artisan migrate --force`，再构建前端、刷新配置/路由/视图缓存。修改初始管理员密码通过此页面进行，不通过 .env 的 ADMIN_PASSWORD 覆盖已有账号。


## V1.8 管理员授权登录家长前台

- `/admin/parents` 每行新增“授权登录”，已登录管理员点击后直接在新窗口打开配置 `APP_URL` 下的 `/app/authorized-login`，不再弹窗确认或要求再次输入密码。正式环境 `APP_URL=https://www.shengxue360.com`，不需要放宽双域名 Nginx 隔离或 Cookie 域。
- 此功能按既有管理员权限默认仅查看，不能代填、修改或删除学生、考试、意向，也不能修改家长密码；页面隐藏写入口，服务端统一返回 `403 / AUTHORIZED_READ_ONLY`。可查看本人目标家长名下资料，跨家长仍返回 403。
- 后台 `POST /api/v1/admin/parents/{id}/authorize-login` 无需提交密码，校验 admin 会话、CSRF 并限流。使用 64 位随机凭证，服务端以 SHA-256 摘要作为缓存键，1 分钟有效；`POST /api/v1/app/auth/authorized-login` 原子消费一次，保留家长端 CSRF 校验。凭证放在 URL fragment，进入页面立即清除，不进入查询参数、localStorage 或审计日志。
- 两域名须共享同一应用数据库、APP_KEY 和支持锁的缓存（生产使用现有 database 缓存）。无需新增数据表或迁移。授权会话最长 30 分钟，每次请求检查目标家长和操作者的 auth_version；任一账号改密后授权失效。
- 授权进入重新生成家长会话，仅切换当前浏览器的家长身份；后台登录与家长其他设备不受影响。家长前台持续显示目标手机号、操作者、仅查看状态、到期时间和“退出授权查看”。退出仅销毁该家长端会话。
- 授权查看可读取待强制改密账号的业务资料，但不解除 must_change_password 标记；家长本人正常登录仍须改密。家长使用自己的密码重新登录后清除授权状态，恢复正常家长权限。
- 操作日志记录签发、成功进入和主动退出：`parent_authorization_issued`、`parent_authorization_started`、`parent_authorization_ended`，包含管理员、目标家长、时间、IP 和只读标识，不记录任何密码或凭证。


## V1.9 学校全称与班级简化

- 学生档案仍保存一所学校，表单名称为“学校全称”，提示“xx区（县）xx学校”。年级和班级为两个独立表单项，分别使用下拉框和数字班号输入框；电脑和手机均独占一行、上下排列，显示如“一年级3班”“初二 3班”，小学班级名称不带“小学”；原有年级范围保留。
- 增加 `students.class_number SMALLINT UNSIGNED NULL`，新建/编辑档案必须填写 1–99 的整数，输入只接受阿拉伯数字。旧档案迁移后班号为 NULL，不虚构班号，编辑时补填。学校、当前年级、姓名、性别和联系电话保持原规则。
- 档案表单不再填写入学年份、入学年级，也不单独重复填写当前年级。当前年级继续以 current_grade 存储，用于班级、考试默认年级和后台筛选。entry_year、entry_grade 改为可空，已有值保留；新页面保存不提交它们。
- 录入成绩不再填写学校。新增时由服务端在学生行锁内读取档案学校并保存学校快照，忽略客户端传入的 school_at_exam；编辑旧成绩保留原快照，不随档案学校变化。考试年级仍可独立选择，版本冲突规则不变。
- 家长首页、我的、后台档案列表/详情统一展示学校与班级。Excel 学生表以“学校全称、班级”替换“当前学校、入学年份、入学年级、当前年级”；意向表同步展示班级，历史成绩列不变。原导出排序与文本格式规则保持。
- 部署先备份并运行 `php artisan migrate --force`（新增 add_class_number_to_students 迁移），再构建前端并刷新缓存。新迁移不修改已有学校、年级、入学资料、考试或 ID；down 仅移除新增班号列，保留旧入学字段可空，避免为新记录伪造年份。


## V1.10 成绩只填写分数

- 每科分数或等级二选一；分数模式只填写“分数”，范围 0–10000、最多两位小数，不再填写满分或限制为 100 分。新保存科目的 max_score 为 NULL，忽略旧客户端传入的该字段；数据库历史值与软删除记录保留，不新增迁移。家长端、后台、图表与 Excel 均展示原分数，单科统计返回 mean_score、best_score 与分数趋势，不再计算或展示满分和得分率；等级独立统计。不增加跨考试平均总分或最高总分比较。
- 编辑考试仍按原有事务软删除旧科目并保存新科目，旧行的分数、满分与关联保留；历史学校快照、版本冲突及权限规则不变。
- 部署更新 PHP 源码并重新构建前端，刷新应用缓存；本次无数据库迁移，不能清空或补造旧成绩。


## V1.11 已被录取学校

- 学生档案新增“已被录取学校”单选选填项（admitted_school），固定选项依次为：宜宾市一中、宜宾市三中、宜宾市四中、宜宾市五中、宜宾市六中、三江成外k12、叙州二中巴蜀班、凉水井中学、三江一中。默认空，可改选或清空；只表示家长记录的录取学校，不改变当前就读学校、考试学校快照或意向。前后端校验固定选项，旧客户端更新时未传字段则保留原值，传 NULL/空字符串可清空。
- 新增 `students.admitted_school VARCHAR(160) NULL`，迁移后旧记录为空，保留已有档案、成绩、意向、关联、时间及软删除数据。未填表示未提供信息，不自动判断为未录取。
- 家长在学生档案中填写，“我的”显示已填结果；后台学生列表/详情、家长详情可查看。学生 Excel 工作表末尾追加“已被录取学校”，空值导出为空，保留现有列顺序、筛选及排序。
- 上线需先执行 `php artisan migrate --force`，再构建前端并刷新缓存；本次不包含自动录取、录取审核或学校账号。
