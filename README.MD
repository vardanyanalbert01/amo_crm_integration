# Amo Crm Integration

This project integrates with **amoCRM WebHooks** to automatically handle and log specific events like **deal/contact creation or updates**, and post textual notes to the related cards accordingly.

## 📌 Features

- Handles the following amoCRM webhook events:
    - "Сделка добавлена"
    - "Контакт добавлен"
    - "Сделка изменена"
    - "Контакт изменен"
- Adds a text note to the card (deal or contact) indicating:
    - On **creation**: title, responsible user, and creation timestamp.
    - On **update**: list of changed fields, new values, and update timestamp.
- Logs all incoming requests for debugging.
- Uses token-based authorization to access the amoCRM API.

## 📁 Project Structure

- docker/ 
  - vhost.conf # Apache virtual host config
- public/
  - .htaccess # URL rewriting
  - index.php # Entry point for web requests 
- src/
  - auth.php # Token exchange & refresh logic
  - hook_handler.php # Processes incoming webhooks
  - logger.php # Logs request data to console
  - resource_handler.php # Tracks and compares amoCRM resource states using JSON files.
- .env.example # Sample environment file
- .gitignore
- composer.json
- composer.lock
- Dockerfile # Docker image definition