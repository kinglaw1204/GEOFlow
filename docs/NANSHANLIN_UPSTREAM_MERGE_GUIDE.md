# 南山林 GEOFlow 版本维护与官方升级指南

本文档用于维护南山林官网集成版 GEOFlow。生产环境不要直接在服务器 `git pull` 官方仓库；应先在本地合并、验证、打包，再全量发布到服务器。

## 分支约定

- `main`：跟随官方 GEOFlow 上游版本，尽量保持干净。
- `nanshanlin-prod`：南山林生产定制分支，包含官网嵌入、SSO、分发、内容渲染和南山林知识工程脚本。

## 当前南山林定制范围

### 后台嵌入与 SSO

- `app/Support/GeoFlowSsoToken.php`
- `app/Http/Controllers/Admin/AdminAuthController.php`
- `routes/web.php`
- `config/geoflow.php`
- `.env.example`
- `.env.prod.example`
- `tests/Feature/AdminSsoLoginTest.php`

生产环境必须配置：

```env
GEOFLOW_SSO_SECRET=与官网 .env.production 完全一致的强随机密钥
GEOFLOW_SSO_ADMIN_USERNAME=admin
```

`GEOFLOW_SSO_ADMIN_USERNAME` 必须是 GEOFlow 中已经存在且 `active` 的管理员账号。SSO 不自动创建账号，也不自动提权。

### 南山林官网内容分发与展示

- `app/Services/GeoFlow/DistributionPayloadBuilder.php`
- `app/Services/GeoFlow/ArticleGeoFlowService.php`
- `app/Services/GeoFlow/WorkerExecutionService.php`
- `app/Http/Controllers/Admin/ArticleController.php`
- `app/Http/Controllers/Site/ArticleController.php`
- `app/Support/Site/ArticleHtmlPresenter.php`

这些文件主要处理文章摘要清洗、标题重复清理、官网分发内容字段和前台文章展示还原。

### 南山林知识工程脚本

- `scripts/import_nanshanlin_wall_design_knowledge.php`
- `scripts/import_nanshanlin_geo_knowledge.php`
- `scripts/export_nanshanlin_geoflow_backup.php`
- 文章图片与缩略图导入辅助脚本
- `docs/南山林-背景墙设计尺寸规范入库稿-v1.md`

原则：知识文章图片宁可无图，不要错图。没有语义匹配前，不默认随机配图。

## 官方版本升级流程

1. 确认当前生产分支干净：

```bash
git checkout nanshanlin-prod
git status --short
```

2. 拉取官方上游：

```bash
git fetch origin
```

3. 从官方 `main` 合并：

```bash
git merge origin/main
```

4. 如果有冲突，优先检查这些文件：

```text
app/Http/Controllers/Admin/AdminAuthController.php
routes/web.php
config/geoflow.php
app/Services/GeoFlow/DistributionPayloadBuilder.php
app/Support/Site/ArticleHtmlPresenter.php
docker-compose.prod.yml
.env.prod.example
```

5. 合并后执行本地测试：

```bash
vendor/bin/pint --dirty
php artisan test --compact tests/Feature/AdminSsoLoginTest.php
php artisan route:list --name=admin.sso-login
```

6. 启动或重建本地容器后验收：

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build
docker compose --env-file .env.prod -f docker-compose.prod.yml ps
```

浏览器重点验收：

- 登录南山林官网后台后打开 `/manage/geoflow`，不应二次登录。
- GEOFlow CSS/JS 正常，不出现 `localhost:3000` 请求。
- AI 模型新增/保存、文章新增/编辑/删除、任务创建能正常提交。
- 分发文章到官网后，表格、列表、标题、摘要、图片和分类能还原。

7. 验收通过后再打官网统一发布包，上传服务器并全量更新。

## 生产发布注意事项

- 服务器生产目录只接收本地已验证的发布包。
- 不在服务器生产目录直接处理 Git 冲突。
- 不提交 `.env`、`.env.prod`、`docker-data/`、`storage/`、`backups/`。
- 每次升级前先备份 PostgreSQL、Redis、GEOFlow `storage/` 和官网 SQLite/媒体目录。
- 如果官方版本改动了登录、路由、URL 生成、Asset URL、队列或分发模块，必须重新完整验证 SSO 和官网分发。

