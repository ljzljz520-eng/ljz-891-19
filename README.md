# 星罗授权管理系统 (AuthQuery System)

## 🛠 技术栈
- Frontend: React + Vite + Tailwind CSS (Glassmorphism / Tech Blue)
- Backend: PHP 8.2 + Apache (MVC + PHPMailer)
- Database: MySQL 8.0

## 🚀 启动指南
1. 确保 Docker Desktop 已启动。
2. 在根目录执行：`docker compose up --build`
3. 访问: http://localhost:3891

## 🔗 服务说明
- **前端页面**: http://localhost:3891
- **API 接口**: http://localhost:8891
- **Mysql**: localhost:18891 (root/root)

## 🧪 管理员账号
- 登录地址: `/admin`
- 账号: `admin`
- 密码: `123456`

## ✨ 核心功能
1. **正版查询**: 动态极光背景，支持 QQ/主人 双重验证。
2. **自助更绑**: 集成 PHPMailer 发送真实 QQ 邮件验证码 (SMTP: yuwangifeng@163.com)。
   - 若发送失败，请在 `docker logs auth_backend` 查看 SMTP 错误日志。
3. **后台管理**: 
   - 现代化表格设计 (头像/状态徽章)。
   - 自定义玻璃拟态弹窗 (Modal) 代替原生 Alert。
   - 完备的 CRUD 功能。

## 📝 交付文档
- `SELF_TEST.md`: 完整的自测报告与架构说明。
