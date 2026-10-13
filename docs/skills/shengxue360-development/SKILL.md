---
name: shengxue360-development
description: "维护升学360成绩记录与招生意向采集项目（teacher）的 Laravel 后端、Vue 家长 H5、管理员后台、MySQL 迁移和开发文档。适用于这个项目的开发、修复、数据结构调整与部署配置；修改前读取稳定业务约定和自动生成的项目结构。"
---

# 升学360项目开发

先读 [项目结构快照](references/project-state.md)，确认工作目录与本次修改位置。此 skill 仅用于快照对应的项目，其他项目不要照搬业务规则。

## 开发依据

- 在项目根目录阅读 `docs/开发设计文档.md`；实施和验收细节查 `docs/AI开发指令.md`、`README.md`。
- 冲突以用户最后确认的决定为准。确认的新规则同时更新设计文档和本文件的手写部分。
- 正式系统使用现有 Laravel + Vue 3 / TypeScript；家长 `/app/`、后台 `/admin/`、统一 `public/index.php` 入口。`parent-h5` 与在线预览是旧静态原型。

## 稳定业务约定

- 后台导航：数据总览、家长账号、学生档案、考试批次、科目成绩、学校意向依次平铺；导航列表最后放可展开的“系统设置”，内含“管理员账号”和“操作日志”。进入系统页面时自动展开并显示对应面包屑，窄屏导航支持滚动。

- 后台管理员账号页支持新增和修改密码，管理员同权；写操作验证操作者密码并审计。管理员 auth_version 默认 0，改密递增并只清除目标 admin 会话，修改自己密码后退出；新建账号统一小写，密码不可展示。

- 正式部署采用 `www.shengxue360.com` 家长端、`admin.shengxue360.com` 后台，根域 `shengxue360.com` 跳转 www；保留现有 `/app/`、`/admin/` 路径，域名首页分别跳转对应路径。生产 APP_URL 为 `https://www.shengxue360.com`，SESSION_DOMAIN=null，使 Cookie 按主机隔离。

- 家长使用手机号和密码，不接短信；忘记密码由管理员设置临时密码，下次登录强制修改。重置使该家长旧会话失效，并记录审计，不能记录密码。
- 注册必须主动同意《用户协议与隐私政策》，前后端校验同意和当前版本；服务端保存版本与 UTC DATETIME，历史账号保持 NULL。协议全文公开可读，监护人提示与联系电话保留；改正文须更新版本。注销及撤回申请目前通过电话人工处理。
- 家长可创建多个学生，只能读写本人名下的数据。管理员全局查看、筛选、导出及重置家长密码，不代填或修改学生业务数据。
- 两端独立登录模型、guard 和 Cookie。会话用 `portal + user_id` 区分角色，数字 ID 相同也不能互相清除会话。写接口保留 CSRF。
- 页面未登录跳登录页；API 未登录 401、跨家长访问现存数据 403、不存在或已删除 404、考试版本冲突 409。
- 每科分数或等级二选一；一批考试可混合。以单科分析为主：满分相同可比较原始分，不同满分使用得分率，等级独立统计。不增加跨考试平均总分或最高总分比较。
- 意向只有“优先”和“备选”；学校自由输入；填报持续开放；家长自行修改、删除。软删除及祖先已删除的数据退出查询、统计和导出。
- 七张业务表的 `id` 为第一列、`BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`；业务外键同类型；API 返回数字 ID。存在的 `created_at / updated_at / deleted_at` 使用 DATETIME，按 UTC 保存。手机号仍是字符串。
- 框架会话 ID、缓存键、批次标识保持框架原类型；不要将所有字符串主键强改为数字。
- Excel 使用真正的 XLSX，导出全部符合筛选条件的数据；文本按字符串写入，防止公式执行。

## 修改与验证

先定位控制器、服务、校验、模型与前端，再做最小完整修改。已有数据升级使用新增迁移并保留关联、软删除和审计数据；不能在业务库执行 `migrate:fresh`。

遵守项目 AGENTS.md 引用的 RTK 指令；本机命令使用 `rtk` 前缀及已有 PHP / Node 包装脚本：

| 修改位置 | 必要验证 |
| --- | --- |
| Vue、TypeScript、样式 | `rtk proxy ./scripts/node npm run build`，检查受影响页面 |
| PHP 业务逻辑 | 用 `rtk proxy ./scripts/php artisan test` 执行相关测试；涉及权限时验证两个角色 |
| 迁移、SQL、字段类型 | 在独立 MySQL 测试库验证；SQLite 不能替代 MySQL 特有约束检查 |
| skill、同步脚本、hook | `rtk proxy ./scripts/node npm run skill:test`、同步并校验 skill；无业务代码改动不必重跑全部 PHP 测试 |

部署详见 `deploy/README.md`：正式 HTTPS Nginx、临时证书验证站点、`deploy/env.production.example` 生产模板。统一 public 入口，更新保留已有 APP_KEY 与 .env；不能根据快照推断测试已经通过或服务已部署。

## 自动同步

仓库源文件位于 `docs/skills/shengxue360-development/`，用户安装副本位于 Codex skill 目录。`rtk proxy ./scripts/node npm run skill:install` 完成安装；`skill:sync` 手动刷新。

`.codex/hooks.json` 在 Codex 工具成功执行后以及回合结束时运行同步脚本；`npm run build` 在构建成功后直接运行同步脚本。本项目禁用 npm 依赖安装脚本，因此不依赖 postbuild 生命周期。原生 hook 需按 Codex 的项目与 hook 信任规则启用，未启用时交付前手动执行 `skill:sync`。

自动同步只更新下方摘要、结构快照和已注册的安装副本，不自动推断需求。保留本文件手写规则；不要手改 `references/project-state.md`。新需求修改设计文档后再次同步。

同步不读取 `.env`、凭据、数据库内容、备份、日志或聊天；不自动执行迁移、部署、提交或额外模型请求。外部编辑器保存不触发 Codex 工具 hook，可通过构建或手动同步刷新。

<!-- project-state:start -->

当前设计 V1.7；10 个迁移；112 个受监测源文件。源指纹：`0f88fd96843b37fa`。
详见 [项目结构快照](references/project-state.md)。本段由 hook 更新。

<!-- project-state:end -->
