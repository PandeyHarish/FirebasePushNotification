# Firebase Cloud Messaging Laravel Application

A Laravel web application with integrated Firebase Cloud Messaging (FCM) for push notifications. Includes user authentication, task management, and real-time push notifications.

## Features

- User authentication (login/register)
- Task management system
- Firebase Cloud Messaging push notifications
- Automatic FCM token generation and storage
- Foreground and background notification handling
- Bulk and topic-based notifications

## Prerequisites

- PHP 8.2+
- Composer
- Node.js and npm
- Firebase project account
- Modern browser with service worker support

## Installation

### 1. Clone and Install Dependencies

```bash
git clone <repository-url>
cd firebase_test
composer install
npm install
```

### 2. Environment Setup

Copy the environment file and generate application key:

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Firebase Configuration

#### Get Firebase Config

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Create or select a project
3. Go to **Project Settings** → **General**
4. Add a web app and copy the Firebase configuration

#### Download Service Account Key

1. Go to **Project Settings** → **Service Accounts**
2. Click **Generate new private key**
3. Save the JSON file as `firebase_auth.json` in `storage/app/` directory

#### Update Environment Variables

Add to your `.env` file:

```env
FCM_PROJECT_ID=your-project-id
FIREBASE_CREDENTIALS=storage/app/firebase_auth.json
```

#### Update Firebase Config in Code

Update Firebase configuration in these files with your actual values:

- `resources/views/layouts/app.blade.php`
- `public/firebase-messaging-sw.js`

Replace the placeholder Firebase config object with your actual values.

### 4. Database Setup

```bash
php artisan migrate
```

### 5. Build Assets

```bash
npm run build
```

## Running the Application

### Development Mode

Start the development server:

```bash
php artisan serve
```

In another terminal, start Vite:

```bash
npm run dev
```

Or use the combined dev script:

```bash
composer run dev
```

### Access the Application

- Main Application: http://localhost:8000



## License

MIT License
