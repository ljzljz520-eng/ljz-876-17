# 在线考试题库系统（876）

## 项目类型
- 全栈 Web 项目（`frontend` + `backend`）

## 项目简介
本项目是一个基于 Vue 3 + Laravel 12 的在线考试与题库管理系统，支持多角色登录、题库管理、试卷管理、在线考试与成绩统计。

## 技术栈
### 前端
- Vue 3
- Vite
- Pinia
- Vue Router
- Axios
- TailwindCSS

### 后端
- Laravel 12（PHP 8.2）
- Laravel Sanctum（Token 鉴权）
- MySQL 8.0

### 运行方式
- Docker Compose（推荐，当前项目默认方式）

## 目录结构
```text
876/
├── docker-compose.yml
├── README.md
├── frontend/
│   ├── Dockerfile
│   ├── nginx.conf
│   ├── package.json
│   └── src/
├── backend/
│   ├── Dockerfile
│   ├── composer.json
│   ├── app/
│   └── routes/
├── docs/
│   ├── ARCHITECTURE.md
│   └── Database.sql
├── scripts/
└── evidence/
```

说明：`node_modules/`、`vendor/` 等依赖目录由 Docker 构建时自动安装，不需要打包提交。

## 启动与重建
在仓库根目录执行：

```bash
docker compose down
docker compose up -d --build
docker compose ps
```

## 服务地址
| 服务 | 地址 | 说明 |
|---|---|---|
| 前端 | http://localhost:8080 | 用户界面 |
| 后端 API | http://localhost:9000/api | Laravel API |
| MySQL | localhost:3307 | 数据库端口映射 |

## 测试账号
| 角色 | 邮箱 | 密码 |
|------|-------|----------|
| Admin | admin@example.com | password |
| Teacher | teacher@example.com | password |
| Student | student1@example.com | password |

> 登录页已移除快捷测试账号模块，请手动输入账号密码。

## README 与测试账号清单同步（必跑）
在截图前、提交前执行以下命令：

```bash
node scripts/sync-readme-test-credentials.mjs --manifest qa/.runtime/test-credentials.current.json --readme README.md
node scripts/verify-readme-test-credentials.mjs --manifest qa/.runtime/test-credentials.current.json --readme README.md
```

阻断规则：任一命令失败都应视为 `README_TEST_CREDENTIALS_MISMATCH`，不得继续提交流程。

## 核心功能
1. 用户认证：注册、登录、退出。
2. 题库管理：题目增删改查、分类管理。
3. 试卷管理：试卷创建、编辑、题目关联。
4. 在线考试：开始考试、提交答卷、自动评分。
5. 成绩统计：个人成绩与管理端统计数据。

## 角色权限
| 角色 | 可访问模块 |
|---|---|
| Student | 在线考试、我的成绩 |
| Teacher | 在线考试、我的成绩、题库管理、试卷管理 |
| Admin | 全部功能（含数据统计） |

## 人工验证步骤（建议）
1. 打开登录页：`http://localhost:8080/login`。
2. 使用测试账号手动登录，确认菜单与角色权限一致。
3. 进入题库管理，验证新增/编辑/删除流程。
4. 进入试卷管理，验证题目关联与试卷删除流程。
5. 学生账号完成一次在线考试并查看成绩。
6. Admin 查看统计页数据。
7. API 冒烟：

```bash
docker compose exec backend sh -lc "curl -s -o /tmp/unauth.txt -w '%{http_code}\n' http://localhost:8080/api/exams"
docker compose exec backend sh -lc "curl -s -X POST http://localhost:8080/api/auth/login -H 'Content-Type: application/json' -d '{\"email\":\"admin@example.com\",\"password\":\"password\"}'"
```

预期：未登录访问受保护接口返回 `401`；登录接口返回包含 `token` 的 JSON。

## 安全与质量说明
- 密码为哈希存储（bcrypt）。
- API 使用 Sanctum Token 鉴权。
- 接口包含输入校验与错误处理。
- CORS 与基础限流已配置。

## 数据库说明
当前初始化后包含 10 张核心表（含用户、题目、试卷、考试记录、答案记录等）。

详见：
- `docs/Database.sql`
- `docker-compose.yml` 中 `db-init` 初始化段

## 证据目录
测试与质检证据统一放在 `evidence/`（含 `evidence/run-slot*/`）目录。

---
如需进行质检修复闭环，请配合 `qa/qc-feedback-inbox.md`、`qa/qc-fix-send-template.md`、`qa/qc-fix-loop-template.md` 使用。


## 成绩申诉复核轨迹（新增）

### 功能说明
学生对成绩有异议时可发起申诉，教师与教务（Admin）逐级复核，全程留痕：

1. **学生发起申诉**：在「我的成绩」中对已评分记录点击「成绩申诉」，可选择具体题目或整卷申诉，
   填写申诉原因并上传证据（jpg/png/pdf/doc/docx/txt/zip，最多 5 个、单个 10MB 以内）。
   - 申诉类型：分数异议 / 判题异议 / 异常标记。
2. **教师复核**：在「申诉复核」页处理待办，可选择 **维持、加分、减分、转教务**，
   处理意见必填并留档；单题申诉按题目分值调整并重算总分，整卷申诉直接调整总分（自动夹在 0~试卷总分）。
3. **教务终裁**：被转教务的申诉仅教务（Admin）可处理，可维持/加分/减分，不能再次转办。
4. **成绩单状态**：无论维持、加分、减分还是转教务后终裁，申诉处理完毕后成绩单统一显示 **「复核完成」**；
   处理中显示「复核中」。
5. **复核轨迹**：申诉详情按时间线展示每一位处理人（教师/教务）的动作、调整分值、处理后总分与意见，
   学生与处理人均可查看；证据附件通过鉴权接口下载。

### 数据模型（新增 3 张表）
- `score_appeals`：申诉单（题目、类型、原因、状态、原分/终分/调整值、最终结论、结案人）。
- `score_appeal_evidences`：申诉证据附件。
- `score_appeal_reviews`：复核处理轨迹（每个处理人的动作与意见）。

### 主要接口
| 方法 | 路径 | 说明 |
|---|---|---|
| POST | `/api/score-appeals` | 学生提交申诉（multipart，含证据） |
| GET | `/api/score-appeals/mine` | 学生查看自己的申诉与轨迹 |
| GET | `/api/score-appeals/review` | 教师/教务复核列表（支持 status/type/keyword） |
| GET | `/api/score-appeals/{id}` | 申诉详情（鉴权） |
| POST | `/api/score-appeals/{id}/review` | 复核动作：maintain/add/deduct/transfer |
| GET | `/api/score-appeals/evidences/{id}/download` | 证据下载（鉴权） |

> 后端容器启动时会自动执行 `php artisan migrate --force`，为既有数据库补齐新表。
