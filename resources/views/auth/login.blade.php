@extends('layouts.auth')

@section('content')
<style>
    * {
        box-sizing: border-box;
    }

    .login-wrapper {
        width: 100%;
        max-width: 460px;
        margin: 0 auto;
        padding: 0px;
        position: relative;
        z-index: 10;
    }

    .login-panel {
        background: linear-gradient(135deg, #003d6b 0%, #004d80 50%, #002d52 100%);
        border-radius: 24px;
        padding: 40px 48px;
        box-shadow:
            0 25px 50px -12px rgba(0, 0, 0, 0.4),
            0 0 0 1px rgba(255, 255, 255, 0.1);
        position: relative;
        overflow: hidden;
        animation: panelFadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes panelFadeIn {
        from {
            opacity: 0;
            transform: translateY(20px) scale(0.98);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .login-panel::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    }

    .panel-header {
        text-align: center;
        margin-bottom: 32px;
    }

    .panel-logo {
        max-width: 140px;
        height: auto;
        margin: 0 auto 20px;
        display: block;
        filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.08));
    }

    .panel-title {
        font-size: 28px;
        font-weight: 700;
        color: #ffffff;
        margin: 0 0 8px 0;
        letter-spacing: -0.5px;
    }

    .panel-subtitle {
        font-size: 15px;
        color: #b8c5d1;
        margin: 0;
        font-weight: 400;
        letter-spacing: 0.1px;
    }

    .notification-box {
        padding: 12px 16px;
        border-radius: 12px;
        margin-bottom: 20px;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        animation: notificationSlide 0.3s ease-out;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    @keyframes notificationSlide {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .notification-success {
        background: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0;
    }

    .notification-error {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .notification-close {
        background: none;
        border: none;
        color: inherit;
        font-size: 20px;
        cursor: pointer;
        opacity: 0.6;
        padding: 0;
        margin-left: 16px;
        line-height: 1;
        transition: opacity 0.2s;
    }

    .notification-close:hover {
        opacity: 1;
    }

    .panel-form {
        margin-top: 0;
    }

    .input-section {
        margin-bottom: 20px;
    }

    .input-label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #e5e7eb;
        margin-bottom: 10px;
        letter-spacing: 0.2px;
        text-transform: uppercase;
        font-size: 12px;
    }

    .input-box {
        position: relative;
    }

    .input-icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
        font-size: 16px;
        pointer-events: none;
        transition: color 0.25s ease;
        z-index: 1;
    }

    .input-section:focus-within .input-icon {
        color: #60a5fa;
    }

    .input-field {
        width: 100%;
        height: 48px;
        padding: 0 18px 0 50px;
        border: 1.5px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        font-size: 15px;
        color: #ffffff !important;
        background: rgba(255, 255, 255, 0.1) !important;
        backdrop-filter: blur(10px);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        font-weight: 400;
    }

    .input-field:-webkit-autofill,
    .input-field:-webkit-autofill:hover,
    .input-field:-webkit-autofill:focus,
    .input-field:-webkit-autofill:active {
        -webkit-text-fill-color: #ffffff !important;
        -webkit-box-shadow: 0 0 0px 1000px rgba(255, 255, 255, 0.1) inset !important;
        box-shadow: 0 0 0px 1000px rgba(255, 255, 255, 0.1) inset !important;
        background: rgba(255, 255, 255, 0.1) !important;
        transition: background-color 5000s ease-in-out 0s;
    }

    .input-field:hover {
        background: rgba(255, 255, 255, 0.15) !important;
        border-color: rgba(255, 255, 255, 0.3);
    }

    .input-field:focus {
        outline: none;
        background: rgba(255, 255, 255, 0.15) !important;
        border-color: #60a5fa;
        box-shadow:
            0 0 0 4px rgba(96, 165, 250, 0.2),
            0 4px 12px rgba(0, 0, 0, 0.3);
        transform: translateY(-1px);
    }

    .input-field:not(:placeholder-shown) {
        background: rgba(255, 255, 255, 0.15) !important;
        color: #ffffff !important;
    }

    .input-field::placeholder {
        color: rgba(255, 255, 255, 0.5);
        font-weight: 400;
    }

    .input-error {
        color: #dc2626;
        font-size: 12px;
        margin-top: 8px;
        display: flex;
        align-items: center;
        font-weight: 500;
    }

    .input-error::before {
        content: '•';
        margin-right: 6px;
        font-size: 14px;
    }

    .remember-section {
        display: flex;
        align-items: center;
        margin-bottom: 24px;
    }

    .remember-check {
        width: 18px;
        height: 18px;
        margin-right: 10px;
        cursor: pointer;
        accent-color: #4b5563;
    }

    .remember-text {
        font-size: 14px;
        color: #d1d5db;
        cursor: pointer;
        user-select: none;
        font-weight: 400;
    }

    .login-button {
        width: 100%;
        height: 48px;
        background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
        border: none;
        border-radius: 12px;
        color: #ffffff;
        font-size: 15px;
        font-weight: 600;
        letter-spacing: 0.3px;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow:
            0 4px 16px rgba(96, 165, 250, 0.4),
            0 2px 8px rgba(0, 0, 0, 0.2);
        position: relative;
        overflow: hidden;
    }

    .login-button::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        transition: left 0.5s;
    }

    .login-button:hover {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        transform: translateY(-2px);
        box-shadow:
            0 8px 24px rgba(96, 165, 250, 0.5),
            0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .login-button:hover::before {
        left: 100%;
    }

    .login-button:active {
        transform: translateY(0);
        box-shadow:
            0 4px 12px rgba(0, 0, 0, 0.15),
            0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .login-button:focus {
        outline: none;
        box-shadow:
            0 0 0 4px rgba(0, 0, 0, 0.1),
            0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .login-button.loading {
        pointer-events: none;
        opacity: 0.8;
    }

    .header-overlay {
        background: linear-gradient(135deg, rgba(0, 0, 0, 0.75) 0%, rgba(0, 20, 40, 0.85) 100%);
        opacity: 1;
    }

    @media (max-width: 576px) {
        .login-wrapper {
            padding: 16px;
        }

        .login-panel {
            padding: 40px 32px;
            border-radius: 20px;
        }

        .panel-title {
            font-size: 26px;
        }

        .input-field,
        .login-button {
            height: 48px;
        }
    }

    /* Efecto de profundidad adicional */
    .login-panel::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(0, 0, 0, 0.03) 0%, transparent 70%);
        pointer-events: none;
    }
</style>

<div class="login-wrapper">
    <div class="login-panel">
        <div class="panel-header">
            <img src="{{ contabo_url(env('LOGOS_FOLDER', 'logos'), 'logo.png') }}"
                 onerror="this.onerror=null;this.src='{{ asset('images/Empresas/Empresa1/logo.png') }}'"
                 alt="Logo" class="panel-logo">
        </div>

        @if(Session::has('success'))
            <div class="notification-box notification-success">
                <span>{{Session::get('success')}}</span>
                <button type="button" class="notification-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        @endif

        @if(Session::has('success_pass'))
            <div class="notification-box notification-success">
                <span>{{Session::get('success_pass')}}</span>
                <button type="button" class="notification-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        @endif

        @if ($errors->has('error_message'))
            <div class="notification-box notification-error">
                <span>{{ $errors->first('error_message') }}</span>
                <button type="button" class="notification-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="panel-form" id="loginForm">
            {{ csrf_field() }}

            <div class="input-section">
                <label class="input-label" for="username">Usuario</label>
                <div class="input-box">
                    <i class="fas fa-user input-icon"></i>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="input-field"
                        placeholder="Ingresa tu usuario"
                        value="{{ old('username') }}"
                        autocomplete="username"
                        required
                    >
                </div>
                @if ($errors->has('username'))
                    <div class="input-error">{{ $errors->first('username') }}</div>
                @endif
            </div>

            <div class="input-section">
                <label class="input-label" for="password">Contraseña</label>
                <div class="input-box">
                    <i class="fas fa-lock input-icon"></i>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="input-field"
                        placeholder="Ingresa tu contraseña"
                        autocomplete="current-password"
                        required
                    >
                </div>
                @if ($errors->has('password'))
                    <div class="input-error">{{ $errors->first('password') }}</div>
                @endif
            </div>

            <div class="remember-section">
                <input
                    type="checkbox"
                    id="remember"
                    name="remember"
                    class="remember-check"
                    {{ old('remember') ? 'checked' : '' }}
                >
                <label for="remember" class="remember-text">[Recordarme]</label>
            </div>

            <button type="submit" class="login-button" id="submitBtn">
                Iniciar Sesión
            </button>
        </form>
    </div>
</div>

<script>
    document.getElementById('loginForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.classList.add('loading');
        btn.textContent = 'Iniciando...';
    });
</script>

@endsection
