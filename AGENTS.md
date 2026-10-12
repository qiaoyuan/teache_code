# 升学360项目指令

@/Users/qiaoyuan/.codex/RTK.md

修改前读取 `docs/skills/shengxue360-development/SKILL.md`，当前结构见其 `references/project-state.md`。

以用户最后确认的决定为准；新增业务约定同步 `docs/开发设计文档.md`、`docs/AI开发指令.md` 和 skill 的手写部分。正式源码为现有 Laravel + Vue 项目，静态原型只作参考。

项目 hook 会刷新 skill 摘要及结构。hook 未启用时，交付前执行 `rtk proxy ./scripts/node npm run skill:sync`；前端构建成功也会自动同步。

自动快照不能作为测试通过或需求已确认的证据。不要将 `.env`、凭据、业务数据、数据库备份和聊天内容复制进 skill。数据升级必须保留已有记录；测试使用独立库，不能在业务库执行 `migrate:fresh`。
