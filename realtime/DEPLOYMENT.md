# Nextcloud Mail 实时收信 —— 部署与升级文档

本目录(`apps-extra/yoomail/realtime/`)是 Nextcloud Mail 的实时收信扩展服务,基于 IMAP IDLE + Workerman WebSocket 实现接近 Foxmail 的秒级收信体验。

## 功能

- IMAP IDLE 长连接,新邮件秒级感知
- 复用 Nextcloud Mail 现有同步(`occ yoomail:account:sync`)落库
- WebSocket 推送 `mailbox-changed` 给在线浏览器
- Mail 前端收到推送后自动刷新当前邮箱列表
- 支持断线重连、退避、IDLE 保活

## 架构

```text
新邮件
  -> mail-idle worker (每账号一个进程, 阻塞 IMAP IDLE)
  -> occ yoomail:account:sync <id> (独立进程同步, 落库)
  -> IPC worker (tcp://127.0.0.1:8790)
  -> Channel server (tcp://127.0.0.1:2207)
  -> mail-ws worker (websocket://127.0.0.1:8789)
  -> 浏览器 realtime.js -> syncEnvelopes() -> 列表刷新
```

端口:
- 8789 : WebSocket(nginx 反代 `/yoomail-ws` 到此处)
- 8790 : IPC(内部,IDLE worker 上报变化)
- 2207 : Channel(内部 pub/sub)

## 目录结构

```text
apps-extra/yoomail/
  realtime/
    server.php                  # CLI 入口
    lib/                        # 服务类(独立命名空间 OCA\YooMailRealtime)
      RealtimeServer.php
      WebSocketServer.php
      ImapIdleClient.php        # 纯协议 IMAP IDLE(不依赖 Horde)
      ImapIdleConnection.php
      ImapIdleChild.php         # 每账号一个阻塞 IDLE
      ImapIdleManager.php
      RealtimeSyncService.php   # 桥接 occ 同步
      RealtimeTokenService.php  # HMAC token
      UserConnectionRegistry.php
    vendor/                     # Workerman + channel(独立 composer, 不碰 Mail vendor)
    composer.json               # 独立 composer 项目
  lib/Controller/RealtimeController.php  # 发 token 的 HTTP API(少量, 升级要跟进)
  appinfo/routes.php            # 加了 realtime#token 路由(少量)
```

## 部署步骤

### 1. 依赖(已装好)

```bash
cd apps-extra/yoomail/realtime
composer install --no-dev
```

### 2. 安装 systemd 服务

使用应用根目录的 `deploy.sh` 一键部署(以 root 运行):

```bash
# 在 yoomail 应用根目录
sudo bash deploy.sh /path/to/nextcloud
```

脚本会检查 root、展示 Nextcloud 目录并请求确认、自动探测 PHP 运行用户(从 `config/config.php`,如 `www-data`/`apache`)、安装 `/etc/systemd/system/yoomail.service` 并启动服务。

非交互(CI)环境可用:`YOOMAIL_ASSUME_YES=1 sudo bash deploy.sh /path/to/nextcloud`

### 3. nginx 反代

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

### 4. 前端

`js/yoomail.js` 已由源码构建并替换(包含 realtime 集成)。前端源码改动:
- `src/main.js`:暴露 `window.OCA.YooMailRealtime.getMainStore`,并 `import './realtime.js'`
- `src/realtime.js`:新增 WS 客户端(`/apps/yoomail/api/realtime/token`)

前端源码在 `mail-src/`(开发目录),修改后需 `npm run build` 重新构建,产物在 `js/`。

## 常用命令

```bash
sudo systemctl status yoomail
sudo journalctl -u yoomail -f
sudo systemctl restart yoomail
sudo -u www-data php apps-extra/yoomail/realtime/server.php start
```

## 升级 Mail 后需要重新应用的修改

因为 `appinfo/routes.php`、`lib/Controller/RealtimeController.php`、`js/*` 是 Mail 的既有文件(会被升级覆盖),升级 Mail 版本后需重新:

1. **routes.php**:在 `'routes' => [...]` 数组内(末尾)加:
   ```php
   [
       'name' => 'realtime#token',
       'url' => '/api/realtime/token',
       'verb' => 'POST',
   ],
   ```
2. **RealtimeController.php**:从本项目 `lib/Controller/RealtimeController.php` 复制过去
3. **前端**:从本项目 `mail-src/src/*` 的改动重新构建,或直接复制本项目构建好的整个 `js/` 目录
4. `realtime/` 目录本身不受 Mail 升级影响,无需动

## 配置项(可选)

实时服务配置存储在数据库 app config(`oc_appconfig` 表),键名以 `yoomail.realtime_*` 为前缀,默认值如下。后续可在后台设置页修改(预留)。

| 键 | 默认值 | 说明 |
|----|--------|------|
| `yoomail.realtime.ws_host` | `127.0.0.1` | WebSocket 监听地址 |
| `yoomail.realtime.ws_port` | `8789` | WebSocket 端口 |
| `yoomail.realtime.ipc_port` | `8790` | IPC 端口 |
| `yoomail.realtime.channel_port` | `2207` | 内部 Channel 端口 |
| `yoomail.realtime.ws_public_url` | *(空)* | 如独立域名反代,填 `wss://.../yoomail-ws` |
| `yoomail.realtime.idle_refresh_seconds` | `1500` | IMAP IDLE 刷新间隔(秒) |

> 修改后重启实时服务生效:`sudo systemctl restart yoomail`

## 限制(第一版)

- 只监听 INBOX
- 只支持密码认证账号(腾讯企业邮箱/客户端授权码)。Gmail 走 OAuth2 的暂不支持,回退轮询
- 每个账号占一条 IMAP 长连接

## 安全

- WebSocket 只绑定 localhost,公网经 nginx 反代
- token 为 HMAC 签名 + 60 秒过期,不暴露 IMAP 密码
- WS 只推送事件 metadata,不推送邮件正文(正文仍走现有 Mail API)
