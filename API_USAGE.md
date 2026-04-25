# TaskForge API Documentation

This project provides a robust RESTful API for managing projects and tasks.

## 🔐 Authentication
The API uses **Laravel Sanctum** (Bearer Tokens).
1. **Login**: `POST /api/login` with `email` and `password`.
2. **Response**: Receive a `token`.
3. **Usage**: Include the header `Authorization: Bearer <your_token>` in all requests.

---

## 📁 Projects
| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/api/projects` | List all projects (cached) |
| `POST` | `/api/projects` | Create a new project |
| `GET` | `/api/projects/{id}` | Get project details |
| `PUT` | `/api/projects/{id}` | Update project |
| `DELETE` | `/api/projects/{id}` | Delete project |

---

## 🎯 Tasks
| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/api/projects/{id}/tasks` | List tasks for a project |
| `POST` | `/api/projects/{id}/tasks` | Create task (triggers Queue & Broadcast) |
| `PUT` | `/api/tasks/{id}` | Update task status/priority |
| `DELETE` | `/api/tasks/{id}` | Remove task |

---

## ⚡ Scalability Features
- **Caching**: Project lists are cached for 60 minutes.
- **Rate Limiting**: 60 requests per minute per user.
- **Background Jobs**: Task assignments are processed via queues.
- **Real-time**: Private channels (Pusher/Reverb) broadcast task updates.

---

## 🛠️ Testing with cURL
```bash
# Login
curl -X POST http://127.0.0.1:8000/api/login \
     -H "Content-Type: application/json" \
     -d '{"email":"admin@taskforge.app", "password":"123456789"}'

# Get Projects
curl -X GET http://127.0.0.1:8000/api/projects \
     -H "Authorization: Bearer YOUR_TOKEN_HERE"
```
