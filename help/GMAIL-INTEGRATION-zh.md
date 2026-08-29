# Gmail 接入说明

本文记录 YooMail 接入 Gmail 的两种方式,用于后续测试、部署和用户文案调整。

## 结论

YooMail 收发 Gmail 邮件有两条路径:

- 普通用户可使用 Google 应用专用密码。
- 管理员可配置 Google OAuth,配置完成后用户通过 Google 授权连接。

两种方式配置成功后,邮件收取、发信、文件夹、附件等业务能力都仍然基于 Gmail IMAP/SMTP,最终体验应保持一致。差异主要在认证方式。

## 方式一: Google 应用专用密码

这是普通用户最容易自助完成的方式。

用户不需要 Google Cloud,也不需要创建 OAuth Client。

基本流程:

1. 用户登录自己的 Google 账号。
2. 开启两步验证。
3. 打开 Google 应用专用密码页面。
4. 生成一个用于邮件客户端的应用专用密码。
5. 回到 YooMail 添加 Gmail 账号。
6. 邮箱填写 Gmail 地址,密码填写应用专用密码,不是 Gmail 登录密码。

直达入口:

```text
https://myaccount.google.com/apppasswords
```

注意:

- Google 菜单入口可能随账号类型和语言变化,文案不要写死为某个固定菜单路径。
- Workspace 管理员可能禁用应用专用密码。
- 启用高级保护计划的账号可能无法使用应用专用密码。
- 如果用户只填写 Gmail 登录密码,Google 通常会拒绝 IMAP/SMTP 登录,YooMail 可能显示“用户名或密码错误”。

## 方式二: Google OAuth

这是体验更现代的方式,但复杂度在管理员侧。

管理员需要在 Google Cloud Console 创建 OAuth Client,然后在 YooMail 后台保存 Client ID 和 Client secret。

YooMail 后台入口:

```text
/settings/admin/yoomail
```

Google Cloud Console 配置:

- OAuth Client 类型选择 Web application。
- Authorized JavaScript origins 填站点 origin,例如:

```text
https://beta.office.ebf.cc
```

- Authorized redirect URIs 填 YooMail 回调地址,例如:

```text
https://beta.office.ebf.cc/apps/yoomail/integration/google-auth
```

注意:

- JavaScript origin 不能包含路径。
- Redirect URI 必须和 YooMail 后台显示的地址完全一致,包括协议、域名和路径。
- OAuth 应用处于 Testing 状态时,需要把测试 Gmail 账号加入 Test users。
- 若要面向不特定用户正式开放,Google 可能要求完成 OAuth 应用验证。

## 当前实现状态

当前 YooMail 已将 Google OAuth 回调 route 收敛到 YooMail 自己的应用路径:

```text
/apps/yoomail/integration/google-auth
```

相关后端 route 名称:

```text
yoomail.googleIntegration.oauthRedirect
```

同时,Microsoft OAuth 的回调 route 也已从原 Mail route 调整为 YooMail route:

```text
yoomail.microsoftIntegration.oauthRedirect
```

当前后台基础配置页由 `src/admin-basic-settings.js` 维护,通过 webpack 构建生成 `js/admin-basic-settings.js`。Gmail OAuth 配置已补充到该页面,包括:

- 回调 URI 展示。
- Client ID 输入。
- Client secret 输入。
- Save。
- Unlink。

## 产品建议

面向普通用户时,默认说明应优先引导“应用专用密码”。如果管理员已经配置 OAuth,则优先展示“使用 Google 授权连接”。

不要让普通用户以为必须进入 Google Cloud Console 配置 OAuth。这是管理员能力,不是普通用户流程。
