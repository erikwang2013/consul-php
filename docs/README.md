# erikwang2013/consul-php 文档

## 快速导航

| 文档 | 说明 |
|------|------|
| [README.md](../README.md) | 项目总览、快速开始、完整 API |
| [README · 项目结构](../README.md#项目结构) | 目录结构与各模块职责 |
| [README · 架构设计](../README.md#架构设计) | 分层架构说明 |
| [README · 功能设计](../README.md#功能设计) | 能力地图与对应入口 |
| [README · 生命周期](../README.md#生命周期) | 服务实例 / 配置热更新 / 单次请求 |
| [Laravel 集成](../README.md#laravel) | Laravel 框架集成指南 |
| [Hyperf 集成](../README.md#hyperf) | Hyperf 框架集成指南 |
| [webman 集成](../README.md#webman) | webman 框架集成指南 |
| [ThinkPHP 集成](../README.md#thinkphp) | ThinkPHP 框架集成指南 |
| [设计文档](superpowers/specs/2026-05-14-consul-php-design.md) | 架构设计 |
| [实现计划](superpowers/plans/2026-05-14-consul-php.md) | 实现任务拆解 |

---

## 多语言（i18n）

README 已翻译为 12 种语言，每份含本地化的四张设计图（`docs/i18n/<lang>/images/`）：

| 语言 | 文件 | 语言 | 文件 |
|------|------|------|------|
| English | [en](i18n/en/README.md) | Русский | [ru](i18n/ru/README.md) |
| 日本語 | [ja](i18n/ja/README.md) | العربية | [ar](i18n/ar/README.md) |
| 한국어 | [ko](i18n/ko/README.md) | हिन्दी | [hi](i18n/hi/README.md) |
| Deutsch | [de](i18n/de/README.md) | বাংলা | [bn](i18n/bn/README.md) |
| Français | [fr](i18n/fr/README.md) | Bahasa Indonesia | [id](i18n/id/README.md) |
| Español | [es](i18n/es/README.md) | 中文（源） | [../README.md](../README.md) |
| Português | [pt](i18n/pt/README.md) | | |

新增语言：复制 `docs/i18n/strings.template.json` 翻译后，`php scripts/i18n-svg.php build <lang>` 生成图片，`verify <lang>` 自检。

---

## 图示（SVG）

| 图 | 文件 | 说明 |
|------|------|------|
| 项目宠物 Consu | [images/pet.svg](images/pet.svg) | 项目形象：天线=健康检查，胸前脉线=服务状态，腰牌=ACL Token；终端版见 `composer pet` / `Consul\Support\Pet` |
| 架构设计 | [images/architecture.svg](images/architecture.svg) | 应用层 → 集成层 → 客户端 → 高层封装 → API 模块 → 传输层 → PSR 抽象 → Consul Agent |
| 功能设计 | [images/features.svg](images/features.svg) | 18 个 API 模块 + 3 个高层封装 + 4 框架适配的能力地图 |
| 生命周期 | [images/lifecycle.svg](images/lifecycle.svg) | 服务实例状态迁移 · 配置热更新降级恢复 · 单次请求链路 |

---

## Support

If this library is helpful to you, your support is greatly appreciated!

| WeChat Pay | Alipay |
|:---:|:---:|
| <img src="./weixinpay.png" width="130" height="130" alt="WeChat Pay" title="WeChat Pay"> | <img src="./alipay.png" width="130" height="130" alt="Alipay" title="Alipay"> |
