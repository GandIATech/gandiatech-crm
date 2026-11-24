-- 002: índices para los listados de tareas por proyecto y horas por usuario.
CREATE INDEX idx_tasks_project ON tasks(project_id);
CREATE INDEX idx_time_entries_user_date ON time_entries(user_id, date);
