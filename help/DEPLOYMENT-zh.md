# YooMail 部署文档

本文档负责安装、实时服务部署、反向代理、运维命令以及升级部署时的检查事项。

## 1. 安装应用

手工启用：

```bash
# Debian / Ubuntu
sudo -u www-data php occ app:enable yoomail

# CentOS / RHEL
sudo -u apache php occ app:enable yoomail
```

说明：

- `occ` 必须以 `config/config.php` 的属主用户运行
- 首次启用会创建独立的 `yoomail_*` 数据表

## 2. 安装 realtime 依赖

```bash
cd apps-extra/yoomail/realtime
composer install --no-dev
```

## 3. 安装 systemd 服务

在应用根目录执行：

```bash
sudo bash deploy.sh /path/to/nextcloud
```

脚本会：

1. 检查 root 权限
2. 确认探测到的 Nextcloud 根目录
3. 从 `config/config.php` 自动探测 service 运行用户/组
4. 自动探测 PHP CLI 路径
5. 渲染并安装 `/etc/systemd/system/yoomail.service`
6. 启用并启动服务

可选覆盖参数：

- `YOOMAIL_ASSUME_YES=1`
- `YOOMAIL_SKIP_START=1`
- `YOOMAIL_SERVICE_USER=...`
- `YOOMAIL_SERVICE_GROUP=...`
- `YOOMAIL_PHP_BIN=...`

## 4. nginx 反向代理

```nginx
location /yoomail-ws {
    proxy_pass http://127.0.0.1:8789;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_read_timeout 3600;
    proxy_send_timeout 3600;
}
```

## 5. 常用运维命令

```bash
sudo systemctl status yoomail
sudo journalctl -u yoomail -f
sudo systemctl restart yoomail
```

## 6. 配置项

realtime 配置保存在 app config：

| 键 | 默认值 | 说明 |
|----|--------|------|
| `yoomail.realtime.ws_host` | `127.0.0.1` | WebSocket 地址 |
| `yoomail.realtime.ws_port` | `8789` | WebSocket 端口 |
| `yoomail.realtime.ipc_port` | `8790` | IPC 端口 |
| `yoomail.realtime.channel_port` | `2207` | 内部 Channel 端口 |
| `yoomail.realtime.ws_public_url` | *(空)* | 如公网反代后的 `wss://.../yoomail-ws` |
| `yoomail.realtime.idle_refresh_seconds` | `1500` | IMAP IDLE 刷新间隔 |

修改后：

```bash
sudo systemctl restart yoomail
```

## 7. 升级 / 部署检查清单

后续升级上游 Mail 或重新部署时，优先检查：

1. `appinfo/routes.php` 中 realtime token 路由是否仍存在
2. `lib/Controller/RealtimeController.php` 是否仍存在
3. `js/` 中是否仍是 YooMail 的前端构建产物，而不是被上游覆盖
4. `deploy.sh` 与 `realtime/deploy/yoomail.service.template` 是否仍是 YooMail 版本
5. 服务器迁移后 nginx 的 `/yoomail-ws` 反代是否仍正确

## 8. 当前限制

- IDLE 当前监听 `INBOX`、`Trash`、`Sent`
- 其他文件夹仍依赖列表刷新 / 同步
- 目前主要面向密码认证账号
- 每个监听文件夹会占用一条 IMAP 长连接
