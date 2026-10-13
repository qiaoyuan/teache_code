# 升学360生产部署

本目录提供 Linux + Nginx + PHP 8.4-FPM + MySQL 的单域名部署模板。正式代码为 Laravel 13 + Vue 3，家长 `/app/`、后台 `/admin/`，统一进入 `public/index.php`。这些文件是部署材料，尚未替你安装服务器或开通域名。

## 当前推荐：宝塔双域名部署

使用 `nginx.baota.conf.example`，其优先级高于下文旧单域名示例。家长访问 https://www.shengxue360.com 后进入 /app/；后台访问 https://admin.shengxue360.com 后进入 /admin/。无需修改现有业务路由；家长域名拒绝后台路径，后台域名拒绝家长路径。共享同一套代码、数据库和 public/build。

1. DNS 的 @、www、admin 三条 A 记录指向服务器公网 IP。
2. 宝塔创建一个 PHP 8.4 站点，绑定三个域名；假设代码目录 `/www/wwwroot/shengxue360`，运行目录 `/public`。实际路径不同则替换配置全部 root。
3. 先通过面板申请覆盖三个域名的证书。检查证书存储目录，配置默认使用 `/www/server/panel/vhost/cert/www.shengxue360.com/`。不要在证书文件尚不存在时粘贴 SSL 配置。
4. 备份面板原站点配置，将本模板整体作为该站点配置，只加载一次。不要再保留同域名的其他 server，也不要叠加 enable-php 或面板伪静态 include；本文件已包含入口规则。PHP socket 默认 `/tmp/php-cgi-84.sock`，以实际 PHP 8.4 pool 配置为准。
5. Laravel `.env` 设置 `APP_URL=https://www.shengxue360.com`、`SESSION_DOMAIN=null`、`SESSION_SECURE_COOKIE=true`；保留 APP_KEY，执行 `php artisan config:cache`。CLI 同样选择 PHP 8.4。
6. 宝塔保存前检查配置，或执行 `/www/server/nginx/sbin/nginx -t`；成功后通过面板重载 Nginx。确认面板证书续期任务仍正常，HTTP 的验证路径已保留。

首次部署先按后文安装依赖、迁移和构建资源。此模板不代表已完成 DNS/证书/服务器部署。原 `nginx.conf.example` 和 HTTP bootstrap 文件仅作为旧单域名参考，不与此文件同时加载。

## 旧单域名参考配置

| 配置 | 模板值 | 说明 |
| --- | --- | --- |
| 域名 | `shengxue360.com` | 已确认正式根域；Nginx 与 APP_URL 已配置一致 |
| 项目目录 | `/var/www/shengxue360` | Nginx root 必须以 `/public` 结尾 |
| PHP-FPM | `/run/php/php8.4-fpm.sock` | 改成 PHP 8.4 pool 实际 listen 地址；TCP 可写 `127.0.0.1:9000` |
| 证书 | `/etc/letsencrypt/live/shengxue360.com/` | 配置完整证书链与私钥的实际位置 |
| 数据库 | `127.0.0.1:3306 / shengxue360` | 沿用已确认库名；生产使用专用账号与强密码 |
| 数据库账号 | `shengxue360` | 替换 `.env` 中账号和 CHANGE_ME 密码 |
| FPM 用户组 | `www-data` | 以下权限命令为 Debian/Ubuntu 示例，按实际 FPM 用户组修改 |

模板假设 Nginx 直接终止 HTTPS。若另有 CDN、负载均衡或反向代理，需要按实际代理地址配置 Laravel trusted proxies，不能直接信任所有来源的转发头。

## 一、准备运行环境与数据库

安装 Nginx、PHP 8.4 CLI/FPM、Composer、MySQL。PHP CLI 与 FPM 均需启用 PDO MySQL、mbstring、intl、DOM、XMLReader、XMLWriter、ZIP 等依赖；安装后用 `composer check-platform-reqs --no-dev` 核对完整要求。Node 22.12+ 仅构建前端需要；可在开发机构建后上传 `public/build/`，服务器无需安装 Node。

新服务器由数据库管理员建立数据库及专用账号（已有库不重建）。以下 SQL 中密码必须替换，在数据库管理工具中执行：

```sql
CREATE DATABASE shengxue360 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'shengxue360'@'127.0.0.1' IDENTIFIED BY 'CHANGE_ME_DATABASE_PASSWORD';
GRANT ALL PRIVILEGES ON shengxue360.* TO 'shengxue360'@'127.0.0.1';
```

账号仅授权本应用库，包含迁移所需建表/改表权限；远程数据库按实际连接来源设置用户 Host。不要把本机的 root 空密码直接用于生产。

## 二、首次安装 Laravel

下列命令在服务器项目根目录执行；任一步失败先解决，不继续上线。更新已有系统请用后面的升级步骤。

```sh
cd /var/www/shengxue360
# 仅当不存在 .env 时复制，避免覆盖密钥与生产配置。
test -f .env || cp deploy/env.production.example .env
# 编辑 .env：域名、数据库密码；APP_KEY 暂空。
nano .env
composer install --no-dev --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
php artisan config:clear
php artisan key:generate --force
php artisan migrate --force
php artisan admin:init
```

`key:generate` 只用于全新安装且 APP_KEY 为空时；已有系统必须保留原密钥。`admin:init` 在 ADMIN_PASSWORD 为空时交互询问密码，至少 8 位并含字母和数字；已存在的管理员不会被重置。不要把密码写进命令行历史。初始化完成若曾设置 ADMIN_PASSWORD，应清空后再缓存配置。

前端二选一：

- 服务器构建：执行 `npm ci --ignore-scripts`、`npm run build`。
- 开发机执行上述构建，将本次版本完整的 `public/build/` 一起上传；Git 默认不提交此目录。保留 manifest.json 和全部带哈希名的静态资源。

生产目录不要存在 `public/hot`（Vite 开发标记）。不需要运行 `npm run dev` 或 `php artisan serve`。

假设代码由当前部署用户持有、PHP-FPM 用户组为 www-data，设置运行目录权限：

```sh
sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R g+rwX storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod g+s {} \;
sudo chgrp www-data .env
chmod 640 .env
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

同时确保 Nginx 能读取 public，FPM 能读取应用代码及 vendor，并能遍历项目上层目录；只有 storage 和 bootstrap/cache 需要 FPM 写权限，不使用 chmod 777。运行目录中由部署命令新建的文件也应保持该组可写，可为部署用户使用 `umask 0002`，但 .env 仍单独设为 640。

## 三、启用 HTTPS 与 Nginx

先把域名解析到服务器，并放行 80/443。核对模板中的项目目录、socket、证书路径；不要修改本机开发站点配置。

已有证书时，将 `nginx.conf.example` 安装为 Nginx 实际加载的站点配置，例如 `/etc/nginx/conf.d/shengxue360.conf`。该目录必须已被主配置的 http 块 include。宝塔可将相同 server 内容适配到该站点配置，使用面板的真实证书路径和 PHP 8.4 socket；不要重复定义 server 或再叠加其他 PHP location。

尚无证书时：

1. 先部署 `nginx.http-bootstrap.conf.example`，验证配置后 reload。这个临时站点仅开放证书验证路径，其余返回 503。
2. 使用已安装的证书工具或面板签发证书。例如安装并配置 Certbot 后执行：

```sh
sudo certbot certonly --webroot -w /var/www/shengxue360/public -d shengxue360.com
```

3. 将临时站点文件替换为正式 `nginx.conf.example` 的修改版，保证同一域名只有一份配置；证书路径存在后再测试、加载。

```sh
sudo nginx -t
# 仅上一条成功后执行；PHP-FPM 服务名按实际安装修改。
sudo systemctl reload php8.4-fpm
sudo systemctl reload nginx
```

证书续期需要由证书工具的定时任务及成功后的 Nginx reload hook 负责；安装模板本身不会自动建立续期任务。按实际证书工具配置并验证续期。

生产 `.env` 必须保持 `APP_URL=https://shengxue360.com`、`SESSION_SECURE_COOKIE=true`。模板的 fastcgi HTTPS 参数使 Laravel 正确识别安全请求。两端同源，不需额外 CORS 配置。HTTP 自动跳 HTTPS；协议弹窗允许同源 iframe。

## 四、后续更新

先备份数据库以及受限保存的 `.env`/APP_KEY；数据库备份放在 public 之外。保留上一版代码和构建产物。使用部署用户进入项目目录：

```sh
php artisan down --retry=30
# 在这里上传/拉取已确认版本，保留原 .env、storage 和必要运行目录。
composer install --no-dev --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
# 在这里上传本次 public/build，或者 npm ci --ignore-scripts 后 npm run build。
php artisan config:clear
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
# 检查目录权限并重载 FPM，使新代码与 OPcache 一致。
sudo systemctl reload php8.4-fpm
php artisan up
```

不覆盖 `.env`，不重新生成 APP_KEY，不执行 migrate:fresh。失败时保持维护模式，定位错误；涉及数据库结构变化时，回退代码之前先判断迁移兼容性，必要时完整恢复备份，不盲目 migrate:rollback。当前同步队列无需额外队列 worker。

## 五、上线验证与排错

- `/up` 返回 200 只代表应用可启动；还需验证数据库和业务流程。
- `/app/register` 能打开完整协议；不勾选不能注册，同意后可注册、登录、录入并重新读取学生及成绩。
- `/admin/login` 可登录，统计和 XLSX 导出可用；家长与后台 Cookie 分离。
- 新浏览器访问受保护页面跳登录；家长不能访问他人数据。
- `/.env`、`/composer.json` 不应暴露项目文件；不存在的 `/test.php` 返回 404。
- 访问 HTTP 应跳转 HTTPS；静态资源无 404，页面刷新和深层路由正常。

502 优先检查 FPM socket/权限/服务；419 或登录后掉线检查 HTTPS、Cookie、APP_KEY、会话表及配置缓存；白屏/静态资源 404 检查 public/build/manifest.json、APP_URL 和 public/hot；500 查看 storage/logs 与 Nginx error_log，保持 APP_DEBUG=false。

部署前先使用专用验证账号检查，并明确清理策略；不要向正式业务库导入演示种子数据。生产备份与恢复方法见项目 README。
