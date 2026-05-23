@extends('layouts.app')

@section('title', 'Chat de WhatsApp')

@section('content')
<style>
    /* Make parent containers stretch tightly around the chat panel */
    body:has(#whatsapp-chat-app) .content-wrapper { padding-bottom: 16px !important; }
    body:has(#whatsapp-chat-app) .grid-margin.stretch-card,
    body:has(#whatsapp-chat-app) .card.top-radius,
    body:has(#whatsapp-chat-app) .body-card,
    body:has(#whatsapp-chat-app) main#app { margin-bottom: 0 !important; }
    body:has(#whatsapp-chat-app) .grid-margin { margin-bottom: 0 !important; }

    /* Reset & Base */
    #whatsapp-chat-app {
        font-family: 'Segoe UI', 'Helvetica Neue', Helvetica, Arial, sans-serif;
        height: calc(100vh - 170px); /* JS adjusts dynamically on resize */
        min-height: 520px;
        display: flex;
        flex-direction: column;
        background-color: #f0f2f5;
        border: 1px solid #e1e6ea;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08), 0 2px 6px rgba(15, 23, 42, 0.04);
    }

    .btn-back {
        display: none;
        background: none;
        border: none;
        color: #54656f;
        padding: 6px;
        margin-right: 6px;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        cursor: pointer;
        align-items: center;
        justify-content: center;
    }

    .btn-back:hover {
        background-color: rgba(0, 0, 0, 0.05);
    }

    @media (max-width: 768px) {
        #whatsapp-chat-app {
            border-radius: 8px;
        }
        .chat-sidebar {
            width: 100%;
            border-right: none;
        }
        .chat-layout.has-active .chat-sidebar {
            display: none;
        }
        .chat-layout:not(.has-active) .chat-main {
            display: none;
        }
        .btn-back {
            display: inline-flex;
        }
        .chat-toolbar {
            padding: 10px 14px;
            height: auto;
        }
        .chat-toolbar h1 {
            font-size: 0.95rem;
        }
        .message-bubble {
            max-width: 80%;
        }
    }

    /* Utilities */
    .flex { display: flex; }
    .flex-col { flex-direction: column; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    .justify-center { justify-content: center; }
    .flex-1 { flex: 1; }
    .hidden { display: none !important; }
    .cursor-pointer { cursor: pointer; }
    .relative { position: relative; }

    /* Colors */
    .bg-white { background-color: #fff; }
    .bg-gray-50 { background-color: #f9f9fa; }
    .bg-green-500 { background-color: #00a884; }
    .bg-green-600 { background-color: #008069; }
    .text-white { color: #fff; }
    .text-gray-500 { color: #667781; }
    .text-gray-900 { color: #111b21; }
    
    /* Toolbar */
    .chat-toolbar {
        background: linear-gradient(135deg, #008069 0%, #06b07a 100%);
        color: white;
        padding: 12px 22px;
        display: flex;
        align-items: center; 
        justify-content: space-between;
        height: 64px;
        box-shadow: 0 1px 0 rgba(255,255,255,0.08) inset, 0 2px 6px rgba(0,0,0,0.12);
    }
    
    .chat-toolbar h1 {
        font-size: 1.05rem;
        font-weight: 600;
        margin: 0;
        letter-spacing: 0.2px;
    }

    .instance-selector select {
        padding: 7px 12px;
        border-radius: 8px;
        border: 1px solid rgba(255,255,255,0.25);
        background-color: rgba(255,255,255,0.95);
        font-size: 0.88rem;
        color: #1f2937;
        outline: none;
        transition: box-shadow 0.2s ease;
    }

    .instance-selector select:focus {
        box-shadow: 0 0 0 3px rgba(255,255,255,0.35);
    }

    .toolbar-status {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.8rem;
        opacity: 0.92;
    }

    .toolbar-status .status-dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background-color: #d1d5db;
        box-shadow: 0 0 0 2px rgba(255,255,255,0.15);
    }

    .toolbar-status .status-dot.active {
        background-color: #ffd166;
        box-shadow: 0 0 0 3px rgba(255, 209, 102, 0.25);
    }

    /* Layout */
    .chat-layout {
        display: flex;
        flex: 1;
        overflow: hidden;
        height: 100%;
    }

    /* Sidebar */
    .chat-sidebar {
        width: 360px;
        border-right: 1px solid #e9eef1;
        display: flex;
        flex-direction: column;
        background-color: #fff;
    }

    .search-box {
        padding: 12px 12px 8px;
        border-bottom: 1px solid #f0f2f5;
        background-color: #fff;
    }

    .search-input {
        width: 100%;
        padding: 9px 16px;
        background-color: #f3f5f7;
        border: 1px solid transparent;
        border-radius: 22px;
        font-size: 0.92rem;
        outline: none;
        transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .search-input:focus {
        background-color: #fff;
        border-color: #d4dadf;
        box-shadow: 0 0 0 3px rgba(0, 128, 105, 0.08);
    }

    .chat-list {
        flex: 1;
        overflow-y: auto;
    }

    .chat-item {
        display: flex;
        align-items: center;
        padding: 12px 14px;
        cursor: pointer;
        border-bottom: 1px solid #f3f5f7;
        transition: background-color 0.15s ease;
    }

    .chat-item:hover {
        background-color: #f7f9fa;
    }

    .chat-item.active {
        background-color: #eef6f4;
    }

    .avatar {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        background: linear-gradient(135deg, #00a884 0%, #008069 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: #fff;
        font-size: 1rem;
        flex-shrink: 0;
        box-shadow: 0 1px 2px rgba(0,0,0,0.12);
        letter-spacing: 0.5px;
    }

    .chat-info {
        margin-left: 15px;
        flex: 1;
        overflow: hidden;
    }

    .chat-header-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 3px;
    }

    .chat-name {
        font-weight: 500;
        color: #111b21;
        font-size: 1rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .chat-time {
        font-size: 0.75rem;
        color: #667781;
    }

    .chat-preview {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .last-message {
        font-size: 0.85rem;
        color: #667781;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .unread-badge {
        background-color: #00a884;
        color: white;
        border-radius: 50%;
        min-width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 600;
        margin-left: 5px;
        padding: 0 4px;
    }

    /* Main Chat Area */
    .chat-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background-color: #efeae2; /* WhatsApp Web BG */
        background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png');
        background-repeat: repeat;
        background-size: 400px; 
    }

    /* Placeholder */
    .chat-placeholder {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #41525d;
        text-align: center;
        background-color: #f6f8fa;
        border-bottom: 5px solid #25D366;
        padding: 24px;
    }

    .chat-placeholder h2 {
        margin: 8px 0 4px;
        font-weight: 500;
        color: #1f2c33;
    }

    .chat-placeholder p {
        color: #667781;
        font-size: 0.92rem;
        margin: 0;
    }

    .placeholder-icon svg {
        width: 80px;
        height: 80px;
        color: #aebac1;
    }

    /* Chat Header */
    .active-chat-header {
        height: 64px;
        background-color: #ffffff;
        padding: 0 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid #e9eef1;
    }

    .header-info {
        display: flex;
        align-items: center;
    }
    
    .header-details {
        margin-left: 14px;
    }

    .header-details h3 {
        margin: 0;
        font-size: 0.98rem;
        font-weight: 600;
        color: #111b21;
    }

    .header-details p {
        margin: 0;
        font-size: 0.78rem;
        color: #667781;
    }

    .btn-close {
        background: #fff;
        color: #ef4444;
        border: 1px solid #fecaca;
        padding: 7px 14px;
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 500;
        cursor: pointer;
        transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
    }

    .btn-close:hover {
        background: #ef4444;
        color: #fff;
        border-color: #ef4444;
    }

    /* Message List */
    .messages-container {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .message-row {
        display: flex;
        width: 100%;
        margin-bottom: 8px;
    }

    .message-row.outbound { justify-content: flex-end; }
    .message-row.inbound { justify-content: flex-start; }

    .message-bubble {
        max-width: 65%;
        padding: 8px 10px 6px 12px;
        border-radius: 10px;
        position: relative;
        font-size: 0.92rem;
        line-height: 1.4;
        box-shadow: 0 1px 1px rgba(0,0,0,0.08);
    }

    .message-bubble.outbound {
        background-color: #d9fdd3;
        border-top-right-radius: 2px;
    }

    .message-bubble.inbound {
        background-color: #ffffff;
        border-top-left-radius: 2px;
    }

    .message-content {
        margin-bottom: 5px;
        word-wrap: break-word;
        white-space: pre-wrap;
    }

    .message-content p {
        margin: 0;
        white-space: pre-wrap;
    }
    
    .message-image {
        max-width: 300px;
        max-height: 200px;
        object-fit: cover;
        border-radius: 6px;
        cursor: pointer;
        margin-bottom: 4px;
        transition: transform 0.2s;
    }

    .message-image:hover {
        transform: scale(1.02);
        opacity: 0.95;
    }

    /* Chips de documentos (factura / ingreso / contrato) con estado */
    .message-relations {
        margin-top: 8px;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .relation-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 11px;
        text-decoration: none;
    }

    .relation-badge .status-icon {
        margin-left: 4px;
        display: inline-flex;
        align-items: center;
        gap: 2px;
    }

    .relation-badge.badge-factura.success,
    .relation-badge.badge-ingreso.success {
        background: #e6f9ed;
        color: #1b5e20;
    }

    .relation-badge.badge-factura.warning,
    .relation-badge.badge-ingreso.warning {
        background: #fff8e1;
        color: #ff8f00;
    }

    .relation-badge.badge-factura.error,
    .relation-badge.badge-ingreso.error {
        background: #ffebee;
        color: #c62828;
    }

    .message-meta {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 4px;
        font-size: 0.7rem;
        color: #667781;
        margin-top: -4px; /* Pull up closer to text if possible */
    }
    
    .msg-status.read { color: #53bdeb; }
    .msg-status.sent { color: #8696a0; }

    /* Message Relations Badges */
    .message-relations {
        margin-top: 8px;
    }

    .relation-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 500;
        white-space: nowrap;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        text-decoration: none;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .relation-badge:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        opacity: 0.9;
    }

    .badge-contrato {
        background-color: #e3f2fd;
        color: #1976d2;
        border: 1px solid #90caf9;
    }

    .badge-factura {
        background-color: #fff3e0;
        color: #e65100;
        border: 1px solid #ffb74d;
    }

    .badge-ingreso {
        background-color: #e8f5e9;
        color: #2e7d32;
        border: 1px solid #81c784;
    }

    /* Input Area */
    .chat-input-area {
        min-height: 66px;
        background-color: #f6f7f9;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        border-top: 1px solid #e9eef1;
    }

    .btn-attach {
        color: #54656f;
        background: none;
        border: none;
        cursor: pointer;
        padding: 8px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.15s ease, color 0.15s ease;
    }

    .btn-attach:hover:not(:disabled) {
        background-color: rgba(0, 128, 105, 0.08);
        color: #008069;
    }

    .btn-attach:disabled {
        cursor: not-allowed;
        opacity: 0.5;
    }

    .input-wrapper {
        flex: 1;
        background-color: #fff;
        border-radius: 22px;
        padding: 10px 16px;
        display: flex;
        align-items: center;
        border: 1px solid #e9eef1;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        transition: box-shadow 0.15s ease, border-color 0.15s ease;
    }

    .input-wrapper:focus-within {
        border-color: #cfe6df;
        box-shadow: 0 0 0 3px rgba(0, 128, 105, 0.1);
    }

    .message-input {
        width: 100%;
        border: none;
        outline: none;
        font-size: 0.95rem;
        background: transparent;
        color: #111b21;
    }

    .btn-send {
        background: #e9eef1;
        border: none;
        cursor: pointer;
        padding: 8px;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        color: #8a9aa3;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.15s ease, color 0.15s ease, transform 0.1s ease;
    }
    .btn-send:disabled {
        cursor: not-allowed;
        opacity: 0.7;
    }

    /* Recording UI */
    .recording-bar {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 12px;
        background-color: #fff;
        border-radius: 22px;
        padding: 9px 16px;
        border: 1px solid #fbd5d5;
        box-shadow: 0 1px 2px rgba(239, 68, 68, 0.08);
    }

    .recording-indicator {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        color: #54656f;
        font-variant-numeric: tabular-nums;
    }

    .recording-dot {
        width: 10px;
        height: 10px;
        background-color: #ef4444;
        border-radius: 50%;
        animation: pulse-rec 1.1s ease-in-out infinite;
    }

    @keyframes pulse-rec {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.35; transform: scale(0.85); }
    }

    .recording-btn {
        background: none;
        border: none;
        cursor: pointer;
        padding: 8px;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.15s ease, transform 0.1s ease;
    }

    .recording-btn.cancel {
        color: #ef4444;
    }

    .recording-btn.cancel:hover {
        background-color: rgba(239, 68, 68, 0.1);
    }

    .recording-btn.stop {
        color: #fff;
        background-color: #008069;
        box-shadow: 0 2px 6px rgba(0, 128, 105, 0.25);
    }

    .recording-btn.stop:hover {
        background-color: #006a57;
    }

    .recording-btn.stop:active {
        transform: scale(0.96);
    }

    .recording-btn:disabled {
        cursor: not-allowed;
        opacity: 0.5;
    }
    
    .btn-send.active {
        background-color: #008069;
        color: #fff;
        box-shadow: 0 2px 6px rgba(0, 128, 105, 0.25);
    }

    .btn-send.active:hover {
        background-color: #006a57;
    }

    .btn-send.active:active {
        transform: scale(0.96);
    }

    /* Modal */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.9);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(5px);
        animation: fadeIn 0.2s ease-out;
    }
    
    .modal-content {
        position: relative;
        max-width: 90vw;
        max-height: 90vh;
        display: flex;
        justify-content: center;
        align-items: center;
        animation: zoomIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .modal-content img {
        max-width: 100%;
        max-height: 90vh;
        border-radius: 4px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.5);
    }

    .close-modal-btn {
        position: absolute;
        top: -40px;
        right: -40px;
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        transition: all 0.2s;
    }

    .close-modal-btn:hover {
        background: rgba(255, 255, 255, 0.4);
        transform: rotate(90deg);
    }

    .zoom-controls {
        position: absolute;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0, 0, 0, 0.6);
        padding: 8px 15px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        color: white;
        z-index: 10001;
    }

    .zoom-btn {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        font-size: 1.2rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
    }

    .zoom-btn:hover {
        background: rgba(255, 255, 255, 0.4);
    }

    .zoom-level {
        font-size: 0.9rem;
        min-width: 45px;
        text-align: center;
        font-weight: 500;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes zoomIn {
        from { transform: scale(0.9); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    /* Scrollbar */
    .custom-scroll::-webkit-scrollbar { width: 6px; }
    .custom-scroll::-webkit-scrollbar-track { background-color: transparent; }
    .custom-scroll::-webkit-scrollbar-thumb {
        background-color: rgba(0,0,0,0.15);
        border-radius: 3px;
    }
    .custom-scroll::-webkit-scrollbar-thumb:hover {
        background-color: rgba(0,0,0,0.28);
    }

    /* Error Alert Banner */
    .whatsapp-error-alert {
        background-color: #fff3cd;
        border-left: 4px solid #ffc107;
        color: #856404;
        padding: 10px 16px;
        font-size: 0.88rem;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        animation: fadeIn 0.3s ease-out;
        border-bottom: 1px solid #f0d78e;
    }

    .whatsapp-error-alert.error-critical {
        background-color: #f8d7da;
        border-left-color: #dc3545;
        color: #721c24;
        border-bottom-color: #f1aeb5;
    }

    .whatsapp-error-alert .error-icon {
        font-size: 1.2rem;
        flex-shrink: 0;
        margin-top: 1px;
    }

    .whatsapp-error-alert .error-body {
        flex: 1;
    }

    .whatsapp-error-alert .error-title {
        font-weight: 600;
        margin-bottom: 2px;
    }

    .whatsapp-error-alert .error-detail {
        font-size: 0.82rem;
        opacity: 0.85;
    }
</style>

<div id="whatsapp-chat-app">
    <!-- Top Bar -->
    <div class="chat-toolbar">
        <div class="flex items-center">
            <svg style="width: 24px; height: 24px; margin-right: 10px;" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            <div>
                <h1>Chat WhatsApp Meta</h1>
            </div>
        </div>
        
        <div class="flex items-center">
            <div class="instance-selector" style="margin-right: 15px;">
                <select v-model="selectedInstanceId" @change="changeInstance">
                    <option value="">Seleccionar Instancia...</option>
                    @foreach($instances as $inst)
                    <option value="{{ $inst->id }}">{{ $inst->uuid_whatsapp ? $inst->uuid_whatsapp . ' - ' : '' }}{{ $inst->phone_number_id }}</option>
                    @endforeach
                </select>
            </div>
            
            <div class="toolbar-status">
                <span>@{{ lastUpdate }}</span>
                <span class="status-dot" :class="{ 'active': isPolling }"></span>
            </div>
        </div>
    </div>

    <!-- Main Layout -->
    <div v-if="selectedInstanceId" class="chat-layout" :class="{ 'has-active': selectedConversation }">
        
        <!-- Sidebar -->
        <div class="chat-sidebar">
            <div class="search-box">
                <input 
                    v-model="searchQuery" 
                    type="text" 
                    placeholder="Buscar o empezar un chat nuevo" 
                    class="search-input"
                >
            </div>
            
            <div class="chat-list custom-scroll" ref="chatList">
                <div v-if="loadingConversations" style="text-align: center; padding: 20px; color: #667781;">
                    CARGANDO CHATS...
                </div>
                
                <div v-else-if="conversations.length === 0" style="text-align: center; padding: 20px; color: #667781;">
                    No hay conversaciones.
                </div>

                <div 
                    v-for="conv in filteredConversations" 
                    :key="conv.id"
                    @click="selectConversation(conv)"
                    class="chat-item"
                    :class="{ 'active': selectedConversation && selectedConversation.id === conv.id }"
                >
                    <div class="avatar" :style="avatarStyle(conv.name || conv.phone_number)">
                        @{{ conv.initials }}
                    </div>
                    <div class="chat-info">
                        <div class="chat-header-row">
                            <span class="chat-name">@{{ conv.name }}</span>
                            <span class="chat-time">@{{ formatTime(conv.last_message_at) }}</span>
                        </div>
                        <div class="chat-preview">
                            <span class="last-message">@{{ conv.last_message }}</span>
                            <span v-if="conv.unread_count > 0" class="unread-badge">@{{ conv.unread_count }}</span>
                        </div>
                    </div>
                </div>

                <!-- Load more indicator -->
                <div v-if="loadingMore" style="text-align: center; padding: 12px; color: #667781;">
                    <span style="display:inline-block; width:20px; height:20px; border:3px solid #d1d7db; border-top-color:#00a884; border-radius:50%; animation:spin 0.7s linear infinite; vertical-align:middle;"></span>
                    <span style="margin-left:8px; font-size:0.85rem;">Cargando más...</span>
                </div>
                <div v-else-if="!loadingConversations && !hasMoreConversations && conversations.length > 0"
                     style="text-align:center; padding:10px; color:#aaa; font-size:0.8rem;">
                    No hay más conversaciones
                </div>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="chat-main">
            <!-- No Chat Selected -->
            <div v-if="!selectedConversation" class="chat-placeholder">
                <div class="placeholder-icon" style="margin-bottom: 20px;">
                   <svg style="width:80px; height:80px; fill:#aebac1" viewBox="0 0 24 24">
                        <path d="M12 0C5.373 0 0 5.373 0 12c0 2.126.549 4.12 1.517 5.856L.075 23.905l6.195-1.625A11.954 11.954 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818c-1.848 0-3.614-.5-5.18-1.44l-.37-.222-3.856 1.011.029-3.754-.239-.38A9.79 9.79 0 012.182 12c0-5.414 4.404-9.818 9.818-9.818 5.414 0 9.818 4.404 9.818 9.818 0 5.414-4.404 9.818-9.818 9.818z"/>
                   </svg>
                </div>
                <h2>WhatsApp Web</h2>
                <p>Selecciona una conversación para comenzar a chatear.</p>
            </div>

            <!-- Active Chat -->
            <template v-else>
                <!-- Chat Header -->
                <div class="active-chat-header">
                    <div class="header-info">
                        <button class="btn-back" type="button" title="Volver" @click="goBackToList">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                            </svg>
                        </button>
                        <div class="avatar" style="width: 42px; height: 42px;" :style="avatarStyle(selectedConversation.name || selectedConversation.phone_number)">
                            @{{ selectedConversation.initials }}
                        </div>
                        <div class="header-details">
                            <h3>@{{ selectedConversation.name }}</h3>
                            <p>@{{ selectedConversation.phone_number }}</p>
                        </div>
                    </div>
                    <div>
                        <button class="btn-close" @click="goBackToList" title="Cerrar panel">
                            Cerrar
                        </button>
                    </div>
                </div>

                <!-- Error Alert Banner -->
                <div v-if="lastMessageError" class="whatsapp-error-alert" :class="{ 'error-critical': lastMessageError.critical }">
                    <span class="error-icon">@{{ lastMessageError.critical ? '🚫' : '⚠️' }}</span>
                    <div class="error-body">
                        <div class="error-title">@{{ lastMessageError.title }}</div>
                        <div class="error-detail">@{{ lastMessageError.detail }}</div>
                    </div>
                </div>

                <!-- Messages -->
                <div ref="messagesContainer" class="messages-container custom-scroll">
                    <div v-if="loadingMessages" style="text-align: center; color: #667781; padding: 10px;">
                        Cargando mensajes...
                    </div>

                    <div v-for="(group, dateStart) in groupedMessages" :key="dateStart">
                        
                        <!-- Date Header -->
                        <div style="display: flex; justify-content: center; margin: 15px 0 10px 0;">
                            <span style="
                                background-color: #e1f3fb; 
                                color: #1f2c33; 
                                font-size: 0.75rem; 
                                padding: 5px 12px; 
                                border-radius: 8px; 
                                box-shadow: 0 1px 0.5px rgba(0,0,0,0.13);
                                text-transform: uppercase;
                            ">
                                @{{ group.label }}
                            </span>
                        </div>

                        <!-- Messages in Group -->
                        <div 
                            v-for="msg in group.messages" 
                            :key="msg.id"
                            class="message-row"
                            :class="msg.direction"
                        >
                            <div class="message-bubble" :class="msg.direction">
                                <!-- Content -->
                                <div class="message-content">
                                    <p v-if="msg.type === 'text' || msg.type === 'template'" style="margin: 0;">@{{ msg.content }}</p>
                                    
                                    <div v-else-if="msg.type === 'image'">
                                        <img :src="msg.media_url" class="message-image" @click="openImage(msg.media_url)">
                                        <p v-if="msg.content" style="margin: 5px 0 0 0;">@{{ msg.content }}</p>
                                    </div>
                                    
                                    <div v-else-if="msg.type === 'document'">
                                        <a :href="msg.media_url" target="_blank" style="color: #008069; text-decoration: none; font-weight: 500;">
                                            📄 @{{ msg.filename || 'Documento' }}
                                        </a>
                                    </div>

                                    <div v-else-if="msg.type === 'audio'">
                                        <audio :src="msg.media_url" controls style="max-width: 250px;"></audio>
                                    </div>

                                    <!-- Badges de relaciones con estado de WhatsApp -->
                                    <div v-if="msg.contrato || msg.factura || msg.ingreso" class="message-relations">
                                        <a
                                            v-if="msg.contrato"
                                            :href="msg.contrato.url"
                                            target="_blank"
                                            class="relation-badge badge-contrato"
                                            :title="'Contrato: ' + msg.contrato.nro"
                                        >
                                            <i class="fas fa-file-contract"></i>
                                            Contrato: @{{ msg.contrato.nro }}
                                        </a>

                                        <a
                                            v-if="msg.factura"
                                            :href="msg.factura.url"
                                            target="_blank"
                                            class="relation-badge badge-factura"
                                            :class="msg.factura.whatsapp_status_class"
                                            :title="'Factura: ' + msg.factura.codigo + ' - ' + msg.factura.whatsapp_status_label"
                                        >
                                            <i class="fas fa-file-invoice-dollar"></i>
                                            Factura: @{{ msg.factura.codigo }}
                                            <span class="status-icon">
                                                <i v-if="msg.factura.whatsapp_status === 'read'" class="fas fa-check-double"></i>
                                                <i v-else-if="msg.factura.whatsapp_status === 'delivered'" class="fas fa-check"></i>
                                                <i v-else-if="msg.factura.whatsapp_status === 'sent'" class="fas fa-paper-plane"></i>
                                                <i v-else class="fas fa-exclamation-triangle"></i>
                                                @{{ msg.factura.whatsapp_status_label }}
                                            </span>
                                        </a>

                                        <a
                                            v-if="msg.ingreso"
                                            :href="msg.ingreso.url"
                                            target="_blank"
                                            class="relation-badge badge-ingreso"
                                            :class="msg.ingreso.whatsapp_status_class"
                                            :title="'Ingreso: ' + msg.ingreso.nro + ' - ' + msg.ingreso.whatsapp_status_label"
                                        >
                                            <i class="fas fa-money-bill-wave"></i>
                                            Ingreso: @{{ msg.ingreso.nro }}
                                            <span class="status-icon">
                                                <i v-if="msg.ingreso.whatsapp_status === 'read'" class="fas fa-check-double"></i>
                                                <i v-else-if="msg.ingreso.whatsapp_status === 'delivered'" class="fas fa-check"></i>
                                                <i v-else-if="msg.ingreso.whatsapp_status === 'sent'" class="fas fa-paper-plane"></i>
                                                <i v-else class="fas fa-exclamation-triangle"></i>
                                                @{{ msg.ingreso.whatsapp_status_label }}
                                            </span>
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Meta -->
                                <div class="message-meta">
                                    <span>@{{ formatTime(msg.created_at) }}</span>
                                    <span v-if="msg.direction === 'outbound'">
                                        <span v-if="msg.status === 'failed'" class="msg-status" style="color: #dc3545;" :title="msg.error_message || 'Error al enviar'">✗</span>
                                        <span v-else-if="msg.status === 'read'" class="msg-status read">✓✓</span>
                                        <span v-else-if="msg.status === 'delivered'" class="msg-status sent">✓✓</span>
                                        <span v-else class="msg-status sent">✓</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input -->
                <div class="chat-input-area">
                    <label class="btn-attach">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M9.183 17.324a1.85 1.85 0 002.605.006l8.846-8.775a4.2 4.2 0 10-5.918-5.961l-8.91 8.841a6.602 6.602 0 109.303 9.376l7.749-7.689a1 1 0 10-1.411-1.422l-7.75 7.69a4.602 4.602 0 01-6.48-6.533L16.065 4.08a2.2 2.2 0 113.1 3.125l-8.783 8.712a.85.85 0 001.2.008l.598.6.002-.002z"></path>
                        </svg>
                        <input type="file" @change="handleFileUpload" accept="image/*" class="hidden">
                    </label>

                    <button
                        v-if="!isRecording"
                        class="btn-attach"
                        title="Grabar audio"
                        type="button"
                        @click="startRecording"
                        :disabled="sending"
                    >
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                            <path d="M12 14a3 3 0 003-3V6a3 3 0 10-6 0v5a3 3 0 003 3zm5-3a1 1 0 10-2 0 3 3 0 11-6 0 1 1 0 10-2 0 5.002 5.002 0 004 4.9V19H9a1 1 0 000 2h6a1 1 0 100-2h-2v-3.1A5.002 5.002 0 0017 11z"></path>
                        </svg>
                    </button>

                    <div v-if="!isRecording" class="input-wrapper">
                        <input 
                            v-model="newMessage" 
                            @keyup.enter="sendMessage"
                            type="text" 
                            placeholder="Escribe un mensaje aquí" 
                            class="message-input"
                            :disabled="sending"
                        >
                    </div>

                    <div v-else class="recording-bar">
                        <button
                            class="recording-btn cancel"
                            type="button"
                            title="Cancelar grabación"
                            @click="cancelRecording"
                        >
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                <path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                        </button>
                        <div class="recording-indicator">
                            <span class="recording-dot"></span>
                            <span>Grabando... @{{ recordingTimeLabel }}</span>
                        </div>
                    </div>

                    <button
                        v-if="!isRecording"
                        @click="sendMessage"
                        class="btn-send"
                        :class="{ 'active': newMessage.trim() && !sending }"
                        :disabled="sending || !newMessage.trim()"
                    >
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                            <path d="M1.101 21.757L23.8 12.028 1.101 2.3l.011 7.912 13.623 1.816-13.623 1.817-.011 7.912z"></path>
                        </svg>
                    </button>

                    <button
                        v-else
                        class="recording-btn stop"
                        type="button"
                        title="Detener y enviar"
                        @click="stopRecording"
                        :disabled="sending"
                    >
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor">
                            <path d="M1.101 21.757L23.8 12.028 1.101 2.3l.011 7.912 13.623 1.816-13.623 1.817-.011 7.912z"></path>
                        </svg>
                    </button>
                </div>
            </template>
        </div>
    </div>

    <!-- Empty State (No Instance) -->
    <div v-else style="flex: 1; display: flex; align-items: center; justify-content: center; background-color: #f0f2f5;">
        <div style="text-align: center; color: #667781;">
             <svg style="width: 64px; height: 64px; margin-bottom: 20px;" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
            </svg>
            <h2>Selecciona una instancia</h2>
            <p>Elige una instancia de WhatsApp en el menú superior para cargar tus chats.</p>
        </div>
    </div>

    <!-- Image Modal -->
    <div v-if="imageModalUrl" class="modal-overlay" @click="closeImageModal" @wheel.prevent="wheelZoom">
        <div class="modal-content" @click.stop>
            <img 
                :src="imageModalUrl" 
                :style="{ 
                    transform: 'translate(' + panX + 'px, ' + panY + 'px) scale(' + zoomLevel + ')',
                    cursor: zoomLevel > 1 ? (isDragging ? 'grabbing' : 'grab') : 'default'
                }"
                style="transition: transform 0.1s linear;"
                @mousedown="startDrag"
                @mousemove="onDrag"
                @mouseup="stopDrag"
                @mouseleave="stopDrag"
                draggable="false"
            >
            
            <!-- Close Button -->
            <button 
                @click="closeImageModal"
                class="close-modal-btn"
                title="Cerrar"
            >
                ✕
            </button>

            <!-- Zoom Controls -->
            <div class="zoom-controls" @click.stop>
                <button @click="zoomOut" class="zoom-btn" title="Alejar (-)">-</button>
                <span class="zoom-level">@{{ Math.round(zoomLevel * 100) }}%</span>
                <button @click="zoomIn" class="zoom-btn" title="Acercar (+)">+</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/vue@2.6.14/dist/vue.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

<script>
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Definir rutas desde Laravel para usar en JS
window.routes = {
    conversations: "{{ route('chat.whatsapp.conversations') }}",
    updates: "{{ route('chat.whatsapp.updates') }}",
    messages: (id) => "{{ route('chat.whatsapp.messages', ':id') }}".replace(':id', id),
    send: (id) => "{{ route('chat.whatsapp.send', ':id') }}".replace(':id', id),
    sendImage: (id) => "{{ route('chat.whatsapp.send_image', ':id') }}".replace(':id', id),
    sendAudio: (id) => "{{ route('chat.whatsapp.send_audio', ':id') }}".replace(':id', id),
    assign: (id) => "{{ route('chat.whatsapp.assign', ':id') }}".replace(':id', id),
    close: (id) => "{{ route('chat.whatsapp.close', ':id') }}".replace(':id', id)
};

new Vue({
    el: '#whatsapp-chat-app',
    data: {
        selectedInstanceId: '',
        conversations: [],
        messages: [],
        selectedConversation: null,
        newMessage: '',
        searchQuery: '',
        loadingConversations: false,
        loadingMessages: false,
        sending: false,
        
        // Polling
        pollingInterval: null,
        pollingFrequency: 5000, 
        lastUpdateTimestamp: null,
        lastUpdate: 'Nunca',
        isPolling: false,
        
        // Modal imagen
        imageModalUrl: null,
        zoomLevel: 1,
        
        // Panning
        isDragging: false,
        panX: 0,
        panY: 0,
        startX: 0,
        startY: 0,

        // Pagination
        currentPage: 1,
        lastPage: 1,
        loadingMore: false,

        // Voice recording
        isRecording: false,
        recordingMimeType: '',
        recordingElapsed: 0,
        maxRecordingSeconds: 180,
    },
    
    computed: {
        filteredConversations() {
            const conversations = this.conversations || [];
            if (!this.searchQuery) return conversations;
            
            const query = this.searchQuery.toLowerCase();
            return conversations.filter(c => 
                (c.name && c.name.toLowerCase().includes(query)) ||
                (c.phone_number && c.phone_number.includes(query))
            );
        },

        recordingTimeLabel() {
            const total = this.recordingElapsed || 0;
            const minutes = Math.floor(total / 60).toString().padStart(2, '0');
            const seconds = (total % 60).toString().padStart(2, '0');
            return `${minutes}:${seconds}`;
        },

        hasMoreConversations() {
            return this.currentPage < this.lastPage;
        },

        lastMessageError() {
            if (this.selectedConversation && this.selectedConversation.has_undeliverable_warning) {
                return {
                    title: 'Atención',
                    detail: 'La siguiente linea telefónica según nuestros análisis probablemente no tiene una linea de whatsapp activa, te recomendamos comunicarte y enviar el documento con otra alternativa.',
                    critical: true
                };
            }

            const messages = this.messages || [];
            if (messages.length === 0) return null;

            // Get the last message by created_at
            const sorted = [...messages].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            const lastMsg = sorted[0];

            if (!lastMsg || !lastMsg.error_message) return null;

            return this.translateErrorMessage(lastMsg.error_message);
        },

        groupedMessages() {
            const messages = this.messages || [];
            if (messages.length === 0) return {};

            // 1. Sort by date (oldest first)
            // Filter invalid messages to prevent empty bubbles
            const sorted = messages
                .filter(m => m && m.id && (m.content || m.media_url || m.type === 'location' || m.type === 'contact'))
                .sort((a, b) => new Date(a.created_at) - new Date(b.created_at));

            // 2. Group by date
            const groups = {};
            
            sorted.forEach(msg => {
                const date = new Date(msg.created_at);
                
                // Use ISO date string (YYYY-MM-DD) for robust grouping key
                // This prevents any locale-specific time inclusion in the key
                const dateKey = date.toISOString().split('T')[0];

                // Create a readable label
                let label = '';
                const today = new Date().toISOString().split('T')[0];
                const yesterdayDate = new Date();
                yesterdayDate.setDate(yesterdayDate.getDate() - 1);
                const yesterday = yesterdayDate.toISOString().split('T')[0];

                if (dateKey === today) label = 'Hoy';
                else if (dateKey === yesterday) label = 'Ayer';
                else {
                    // Format like "15 de febrero de 2026"
                    label = date.toLocaleDateString('es-CO', { 
                        weekday: 'long', 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    }); 
                    // Capitalize first letter
                    label = label.charAt(0).toUpperCase() + label.slice(1);
                }

                if (!groups[dateKey]) {
                    groups[dateKey] = {
                        label: label,
                        messages: []
                    };
                }
                groups[dateKey].messages.push(msg);
            });

            return groups;
        }
    },
    
    mounted() {
        console.log('✅ WhatsApp Chat App Mounted');
        
        const instances = @json($instances);
        
        // Buscar instancia por defecto: meta=0 y type=1
        let defaultInstance = instances.find(i => i.meta == 0 && i.type == 1);
        
        // Si no existe, usar la primera disponible
        if (!defaultInstance && instances.length > 0) {
            defaultInstance = instances[0];
        }

        if (defaultInstance) {
            this.selectedInstanceId = defaultInstance.id;
            this.loadConversations();
            this.startPolling();
        }
        
        window.addEventListener('beforeunload', () => {
            this.stopPolling();
        });
        
        // Global mouseup to stop dragging even if mouse leaves image
        window.addEventListener('mouseup', () => {
            this.isDragging = false;
        });

        // Infinite scroll for conversations
        this._convScrollHandler = this.handleConversationsScroll.bind(this);
        this.$nextTick(() => {
            if (this.$refs.chatList) {
                this.$refs.chatList.addEventListener('scroll', this._convScrollHandler);
            }
        });

        // Fallback for browsers without :has() — tighten parent paddings
        const contentWrapper = document.querySelector('.content-wrapper');
        if (contentWrapper) {
            this._prevContentPaddingBottom = contentWrapper.style.paddingBottom;
            contentWrapper.style.paddingBottom = '16px';
        }
        ['.grid-margin', '.stretch-card', '.card.top-radius', '.body-card'].forEach(selector => {
            const node = document.querySelector(selector);
            if (node) node.style.marginBottom = '0px';
        });

        // Dynamic height: fill the available space inside the layout
        this._resizeHandler = this.adjustChatHeight.bind(this);
        window.addEventListener('resize', this._resizeHandler);
        this.$nextTick(() => this.adjustChatHeight());
        // Re-measure shortly after to catch late-loaded fonts/menus
        setTimeout(() => this.adjustChatHeight(), 200);
        setTimeout(() => this.adjustChatHeight(), 800);
    },

    beforeDestroy() {
        if (this.$refs.chatList && this._convScrollHandler) {
            this.$refs.chatList.removeEventListener('scroll', this._convScrollHandler);
        }
        if (this._resizeHandler) {
            window.removeEventListener('resize', this._resizeHandler);
        }
        const contentWrapper = document.querySelector('.content-wrapper');
        if (contentWrapper && typeof this._prevContentPaddingBottom !== 'undefined') {
            contentWrapper.style.paddingBottom = this._prevContentPaddingBottom;
        }
    },
    
    methods: {
        changeInstance() {
            this.stopPolling();
            this.conversations = [];
            this.messages = [];
            this.selectedConversation = null;
            this.lastUpdateTimestamp = null;
            this.currentPage = 1;
            this.lastPage = 1;
            this.loadingMore = false;
            this.searchQuery = '';
            
            if (this.selectedInstanceId) {
                this.loadConversations();
                this.startPolling();

                // Re-attach scroll listener on next tick (ref stays mounted)
                this.$nextTick(() => {
                    if (this.$refs.chatList) {
                        this.$refs.chatList.removeEventListener('scroll', this._convScrollHandler);
                        this.$refs.chatList.addEventListener('scroll', this._convScrollHandler);
                    }
                });
            }
        },
        
        async loadConversations() {
            if (!this.selectedInstanceId) return;
            
            this.loadingConversations = true;
            this.currentPage = 1;
            try {
                const response = await axios.get(window.routes.conversations, {
                    params: { instance_id: this.selectedInstanceId, page: 1, per_page: 20 }
                });
                this.conversations = response.data.data || [];
                // Capture pagination metadata if the API exposes it
                const meta = response.data.meta || response.data;
                this.lastPage = meta.last_page || meta.lastPage || 1;
                console.log('📋 Conversations page 1/' + this.lastPage + ' loaded (' + this.conversations.length + ' items)');
            } catch (error) {
                console.error('Error cargando conversaciones:', error);
            } finally {
                this.loadingConversations = false;
            }
        },

        async loadMoreConversations() {
            if (!this.selectedInstanceId || this.loadingMore || !this.hasMoreConversations) return;

            this.loadingMore = true;
            const nextPage = this.currentPage + 1;
            try {
                const response = await axios.get(window.routes.conversations, {
                    params: { instance_id: this.selectedInstanceId, page: nextPage, per_page: 20 }
                });
                const newConvs = response.data.data || [];
                const meta = response.data.meta || response.data;
                this.lastPage = meta.last_page || meta.lastPage || this.lastPage;

                // Append without duplicates
                const existingIds = new Set(this.conversations.map(c => c.id));
                newConvs.forEach(c => { if (!existingIds.has(c.id)) this.conversations.push(c); });

                this.currentPage = nextPage;
                console.log('📋 Conversations page ' + this.currentPage + '/' + this.lastPage + ' loaded');
            } catch (error) {
                console.error('Error cargando más conversaciones:', error);
            } finally {
                this.loadingMore = false;
            }
        },

        handleConversationsScroll() {
            const el = this.$refs.chatList;
            if (!el) return;
            // Trigger when within 80px of the bottom
            if (el.scrollTop + el.clientHeight >= el.scrollHeight - 80) {
                this.loadMoreConversations();
            }
        },

        
        async selectConversation(conversation) {
            this.selectedConversation = conversation;
            this.loadingMessages = true;
            this.$nextTick(() => this.adjustChatHeight());
            
            try {
                // La API centralizada usa conversation.id para los mensajes, 
                // pasamos instance_id como query param para que el controlador obtenga el token
                const response = await axios.get(window.routes.messages(conversation.id), {
                    params: { instance_id: this.selectedInstanceId }
                });
                this.messages = response.data.data || []; // La API centralizada devuelve los mensajes en 'data'
                this.lastUpdateTimestamp = response.data.timestamp;
                
                conversation.unread_count = 0;
                
                this.$nextTick(() => {
                    this.scrollToBottom();
                });
            } catch (error) {
                console.error('Error cargando mensajes:', error);
                alert('Error al cargar mensajes');
            } finally {
                this.loadingMessages = false;
            }
        },
        
        async sendMessage() {
            if (this.sending || !this.selectedConversation || !this.newMessage.trim()) return;
            
            const message = this.newMessage;
            this.newMessage = '';
            this.sending = true;
            const recipient = this.getConversationRecipient(this.selectedConversation);
            
            if (!recipient) {
                this.sending = false;
                this.newMessage = message;
                alert('No se pudo identificar el destinatario de la conversación.');
                return;
            }
            
            try {
                const response = await axios.post(
                    window.routes.send(encodeURIComponent(recipient)),
                    { 
                        message: message,
                        instance_id: this.selectedInstanceId 
                    }
                );
                
                if (response.data.success) {
                    const msg = response.data.data;
                    // Validate message structure before adding
                    if (msg && msg.id && (msg.content || msg.media_url)) {
                        this.messages.push(msg);
                        this.updateConversationLastMessage(msg);
                        this.$nextTick(() => this.scrollToBottom());
                    }
                }
            } catch (error) {
                console.error('Error enviando mensaje:', error);
                const serverMessage =
                    (error.response && error.response.data && (error.response.data.error || (error.response.data.errors && Object.values(error.response.data.errors).flat().join(', ')))) ||
                    null;
                alert(serverMessage || 'Error al enviar mensaje');
                this.newMessage = message;
            } finally {
                this.sending = false;
            }
        },
        
        async handleFileUpload(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            const formData = new FormData();
            formData.append('image', file);
            
            this.sending = true;
            
            try {
                const response = await axios.post(
                    window.routes.sendImage(this.selectedConversation.id),
                    formData,
                    { headers: { 'Content-Type': 'multipart/form-data' } }
                );
                
                if (response.data.success) {
                    this.messages.push(response.data.data);
                    this.updateConversationLastMessage(response.data.data);
                    this.$nextTick(() => this.scrollToBottom());
                }
            } catch (error) {
                console.error('Error enviando imagen:', error);
                alert('Error al enviar imagen');
            } finally {
                this.sending = false;
                event.target.value = '';
            }
        },

        async startRecording() {
            if (this.isRecording || this.sending) return;
            if (!this.selectedConversation) {
                alert('Selecciona una conversación primero.');
                return;
            }

            if (!navigator.mediaDevices || !window.MediaRecorder) {
                alert('Tu navegador no soporta grabación de audio. Usa Chrome, Edge o Firefox actualizado.');
                return;
            }

            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

                const candidateMimeTypes = [
                    'audio/ogg;codecs=opus',
                    'audio/webm;codecs=opus',
                    'audio/webm',
                    'audio/mp4',
                    'audio/aac'
                ];
                let mimeType = '';
                for (const type of candidateMimeTypes) {
                    if (window.MediaRecorder.isTypeSupported && window.MediaRecorder.isTypeSupported(type)) {
                        mimeType = type;
                        break;
                    }
                }

                const recorder = mimeType
                    ? new MediaRecorder(stream, { mimeType })
                    : new MediaRecorder(stream);

                this._recordedChunks = [];
                this._mediaRecorder = recorder;
                this._mediaStream = stream;
                this._recordingCancelled = false;
                this.recordingMimeType = recorder.mimeType || mimeType || 'audio/webm';

                recorder.ondataavailable = (event) => {
                    if (event.data && event.data.size > 0) {
                        this._recordedChunks.push(event.data);
                    }
                };

                recorder.onstop = () => {
                    this.stopRecordingTimer();
                    this.releaseMediaStream();
                    this.isRecording = false;

                    if (this._recordingCancelled) {
                        this._recordedChunks = [];
                        return;
                    }

                    const chunks = this._recordedChunks || [];
                    if (!chunks.length) {
                        alert('No se pudo capturar audio.');
                        return;
                    }

                    const blob = new Blob(chunks, { type: this.recordingMimeType });
                    this._recordedChunks = [];
                    this.uploadRecordedAudio(blob);
                };

                recorder.onerror = (event) => {
                    console.error('MediaRecorder error:', event.error || event);
                    this.cancelRecording();
                    alert('Error durante la grabación de audio.');
                };

                this.isRecording = true;
                this._recordingStartTime = Date.now();
                this.recordingElapsed = 0;
                recorder.start();

                this._recordingTimer = setInterval(() => {
                    this.recordingElapsed = Math.floor((Date.now() - this._recordingStartTime) / 1000);
                    if (this.recordingElapsed >= this.maxRecordingSeconds) {
                        this.stopRecording();
                    }
                }, 250);
            } catch (error) {
                console.error('No se pudo iniciar la grabación:', error);
                if (error && (error.name === 'NotAllowedError' || error.name === 'SecurityError')) {
                    alert('Debes permitir el acceso al micrófono para grabar audios.');
                } else if (error && error.name === 'NotFoundError') {
                    alert('No se detectó ningún micrófono disponible.');
                } else {
                    alert('No se pudo iniciar la grabación.');
                }
                this.releaseMediaStream();
            }
        },

        stopRecording() {
            if (!this.isRecording || !this._mediaRecorder) return;
            this._recordingCancelled = false;
            try {
                if (this._mediaRecorder.state !== 'inactive') {
                    this._mediaRecorder.stop();
                }
            } catch (error) {
                console.error('Error deteniendo grabación:', error);
                this.releaseMediaStream();
                this.stopRecordingTimer();
                this.isRecording = false;
            }
        },

        cancelRecording() {
            this._recordingCancelled = true;
            if (this._mediaRecorder && this._mediaRecorder.state !== 'inactive') {
                try { this._mediaRecorder.stop(); } catch (e) { /* noop */ }
            } else {
                this.releaseMediaStream();
                this.stopRecordingTimer();
                this._recordedChunks = [];
                this.isRecording = false;
            }
        },

        stopRecordingTimer() {
            if (this._recordingTimer) {
                clearInterval(this._recordingTimer);
                this._recordingTimer = null;
            }
        },

        releaseMediaStream() {
            if (this._mediaStream) {
                try {
                    this._mediaStream.getTracks().forEach(track => track.stop());
                } catch (e) { /* noop */ }
                this._mediaStream = null;
            }
            this._mediaRecorder = null;
        },

        async uploadRecordedAudio(blob) {
            if (!blob || !this.selectedConversation) return;

            const recipient = this.getConversationRecipient(this.selectedConversation);
            if (!recipient) {
                alert('No se pudo identificar el destinatario de la conversación.');
                return;
            }

            const extension = this.getExtensionFromMime(this.recordingMimeType);
            const filename = `voz_${Date.now()}.${extension}`;

            const formData = new FormData();
            formData.append('audio', blob, filename);
            formData.append('instance_id', this.selectedInstanceId);

            this.sending = true;

            try {
                const response = await axios.post(
                    window.routes.sendAudio(encodeURIComponent(recipient)),
                    formData,
                    { headers: { 'Content-Type': 'multipart/form-data' } }
                );

                if (response.data.success) {
                    const msg = response.data.data;
                    if (msg && msg.id && (msg.content || msg.media_url || msg.type === 'audio')) {
                        this.messages.push(msg);
                        this.updateConversationLastMessage({
                            content: '🎵 Audio',
                            created_at: msg.created_at || new Date().toISOString()
                        });
                        this.$nextTick(() => this.scrollToBottom());
                    }
                }
            } catch (error) {
                console.error('Error enviando audio:', error);
                const serverMessage =
                    (error.response && error.response.data && (error.response.data.error || (error.response.data.errors && Object.values(error.response.data.errors).flat().join(', ')))) ||
                    null;
                alert(serverMessage || 'Error al enviar audio');
            } finally {
                this.sending = false;
            }
        },

        getExtensionFromMime(mime) {
            const base = String(mime || '').split(';')[0].trim().toLowerCase();
            const map = {
                'audio/ogg': 'ogg',
                'audio/oga': 'ogg',
                'audio/webm': 'webm',
                'audio/mp4': 'm4a',
                'audio/aac': 'aac',
                'audio/mpeg': 'mp3',
                'audio/amr': 'amr',
                'audio/3gpp': '3gp'
            };
            return map[base] || 'webm';
        },
        
        async closeConversation() {
            if (!confirm('¿Cerrar esta conversación?')) return;
            
            try {
                await axios.post(window.routes.close(this.selectedConversation.id));
                this.selectedConversation.status = 'closed';
                alert('Conversación cerrada');
            } catch (error) {
                console.error('Error cerrando conversación:', error);
                alert('Error al cerrar conversación');
            }
        },
        
        // ========== POLLING ==========
        
        startPolling() {
            if (!this.selectedInstanceId) return;
            
            console.log('🔄 Iniciando polling cada', this.pollingFrequency / 1000, 'segundos');
            
            this.pollingInterval = setInterval(() => {
                this.checkForUpdates();
            }, this.pollingFrequency);
        },
        
        stopPolling() {
            if (this.pollingInterval) {
                clearInterval(this.pollingInterval);
                this.pollingInterval = null;
                this.isPolling = false;
                console.log('⏸️ Polling detenido');
            }
        },
        
        async checkForUpdates() {
            if (!this.lastUpdateTimestamp || !this.selectedInstanceId) {
                this.lastUpdateTimestamp = new Date().toISOString();
                return;
            }
            
            try {
                this.isPolling = true;
                
                const params = {
                    instance_id: this.selectedInstanceId,
                    since: this.lastUpdateTimestamp
                };
                
                if (this.selectedConversation) {
                    params.conversation_id = this.selectedConversation.id;
                }
                
                const response = await axios.get(window.routes.updates, { params });
                
                this.lastUpdateTimestamp = response.data.timestamp;
                this.updateLastUpdateLabel();
                
                // Actualizar conversaciones
                if (response.data.conversations.length > 0) {
                    this.mergeConversations(response.data.conversations);
                }
                
                // Agregar nuevos mensajes
                if (response.data.new_messages.length > 0) {
                    response.data.new_messages.forEach(msg => {
                        // 1. Verificar por ID exacto
                        const existingIndex = this.messages.findIndex(m => m.id === msg.id);
                        if (existingIndex !== -1) {
                            return;
                        }

                        // 2. Verificación "Fuzzy" para mensajes enviados recientemente (evitar duplicados visuales)
                        // Si el mensaje es saliente, tiene el mismo contenido (trim) y sucedió hace poco (< 60 seg)
                        if (msg.direction === 'outbound') {
                            const duplicateIndex = this.messages.findIndex(m => 
                                m.direction === 'outbound' && 
                                (m.content || '').trim() === (msg.content || '').trim() &&
                                // Verificar diferencia de tiempo (relajado a 60s para asegurar captura)
                                Math.abs(new Date(m.created_at) - new Date(msg.created_at)) < 60000 
                            );

                            if (duplicateIndex !== -1) {
                                // Reemplazar el mensaje optimista con el real (que trae el ID correcto, estado, etc.)
                                // Mantener el mensaje si ya tiene ID y el nuevo no (caso raro)
                                const existingMsg = this.messages[duplicateIndex];
                                if (!existingMsg.id || (msg.id && existingMsg.id !== msg.id)) {
                                     this.$set(this.messages, duplicateIndex, msg);
                                }
                                return;
                            }
                        }

                        this.messages.push(msg);
                    });
                    
                    this.$nextTick(() => this.scrollToBottom());
                    
                    if (document.hidden) {
                        this.showNotification('Nuevo mensaje de WhatsApp');
                    }
                }
                
                // Actualizar estados
                if (response.data.updated_statuses.length > 0) {
                    response.data.updated_statuses.forEach(statusUpdate => {
                        const message = this.messages.find(m => m.id === statusUpdate.id);
                        if (message) {
                            message.status = statusUpdate.status;
                            message.delivered_at = statusUpdate.delivered_at;
                            message.read_at = statusUpdate.read_at;
                        }
                    });
                }
                
                // Asegurar reordenamiento después de actualizaciones
                // this.conversations.sort(...) ya se hace en mergeConversations
                
            } catch (error) {
                console.error('Error en polling:', error);
            } finally {
                this.isPolling = false;
            }
        },
        
        mergeConversations(updatedConversations) {
            updatedConversations.forEach(updated => {
                const index = this.conversations.findIndex(c => c.id === updated.id);
                
                if (index !== -1) {
                    this.$set(this.conversations, index, updated);
                } else {
                    this.conversations.unshift(updated);
                }
            });
            
            this.conversations.sort((a, b) => 
                new Date(b.last_message_at) - new Date(a.last_message_at)
            );
        },
        
        updateConversationLastMessage(message) {
            const conv = this.conversations.find(c => c.id === this.selectedConversation.id);
            if (conv) {
                conv.last_message = message.content || 'Media';
                conv.last_message_at = message.created_at;
                
                // Mover al principio
                this.conversations = [
                    conv,
                    ...this.conversations.filter(c => c.id !== conv.id)
                ];
            }
        },
        
        updateLastUpdateLabel() {
            const now = new Date();
            this.lastUpdate = now.toLocaleTimeString('es-CO');
        },
        
        showNotification(message) {
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('WhatsApp Chat', {
                    body: message,
                    icon: '/images/whatsapp-icon.png'
                });
            }
        },
        
        // ========== ERROR TRANSLATION ==========

        translateErrorMessage(rawError) {
            const errorMap = {
                'Business eligibility payment issue': {
                    title: 'Problema de pago en la cuenta de WhatsApp Business',
                    detail: 'La cuenta de WhatsApp Business tiene un problema con el método de pago. Los mensajes no se pueden enviar hasta que se resuelva el pago en la plataforma de Meta.',
                    critical: true
                },
                'Message failed to send because more than 24 hours have passed since the customer last replied to this number': {
                    title: 'Ventana de conversación expirada',
                    detail: 'Han pasado más de 24 horas desde la última respuesta del cliente. Solo se pueden enviar plantillas de mensaje aprobadas.',
                    critical: false
                },
                'Rate limit hit': {
                    title: 'Límite de envío alcanzado',
                    detail: 'Se ha superado el límite de mensajes permitidos. Espere unos minutos antes de intentar enviar nuevamente.',
                    critical: false
                },
                'Recipient phone number not in allowed list': {
                    title: 'Número no permitido',
                    detail: 'El número del destinatario no está en la lista de números permitidos. Verifique la configuración de la cuenta.',
                    critical: false
                },
                'Media download error': {
                    title: 'Error al descargar multimedia',
                    detail: 'No se pudo descargar el archivo multimedia adjunto. Intente enviar el mensaje nuevamente.',
                    critical: false
                }
            };

            // Exact match
            if (errorMap[rawError]) {
                return errorMap[rawError];
            }

            // Partial match (some errors may have extra details appended)
            for (const key in errorMap) {
                if (rawError.toLowerCase().includes(key.toLowerCase())) {
                    return errorMap[key];
                }
            }

            // Fallback: show raw error in a user-friendly way
            return {
                title: 'Error en el envío del mensaje',
                detail: rawError,
                critical: false
            };
        },

        // ========== UTILIDADES ==========
        
        scrollToBottom() {
            const container = this.$refs.messagesContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },
        
        formatTime(timestamp) {
            if (!timestamp) return '';
            
            const date = new Date(timestamp);
            if (isNaN(date.getTime())) return '';

            const now = new Date();

            // Build calendar date strings in local time (YYYY-MM-DD)
            const toYMD = (d) => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
            const msgDay  = toYMD(date);
            const today   = toYMD(now);

            const yesterdayDate = new Date(now);
            yesterdayDate.setDate(now.getDate() - 1);
            const yesterday = toYMD(yesterdayDate);

            // Start of the current week (Monday)
            const startOfWeek = new Date(now);
            startOfWeek.setDate(now.getDate() - ((now.getDay() + 6) % 7)); // Monday = 0
            startOfWeek.setHours(0, 0, 0, 0);

            if (msgDay === today) {
                // Same day: show time (e.g. "9:37 a. m.")
                return date.toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit', hour12: true });
            } else if (msgDay === yesterday) {
                return 'Ayer';
            } else if (date >= startOfWeek) {
                // Within the current week (but not today/yesterday): show weekday name
                const days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                return days[date.getDay()];
            } else {
                // Older: show short date DD/MM/YY
                const dd = String(date.getDate()).padStart(2, '0');
                const mm = String(date.getMonth() + 1).padStart(2, '0');
                const yy = String(date.getFullYear()).slice(-2);
                return `${dd}/${mm}/${yy}`;
            }
        },

        getConversationRecipient(conversation) {
            if (!conversation) return '';

            const candidate = String(conversation.phone_number || conversation.id || '').trim();
            if (!candidate) return '';

            const digits = candidate.replace(/\D+/g, '');
            return digits.length >= 7 ? digits : candidate;
        },

        avatarStyle(seed) {
            const text = String(seed || '?').trim() || '?';
            let hash = 0;
            for (let i = 0; i < text.length; i++) {
                hash = (hash * 31 + text.charCodeAt(i)) >>> 0;
            }
            const hue = hash % 360;
            const hue2 = (hue + 28) % 360;
            return {
                background: `linear-gradient(135deg, hsl(${hue}, 55%, 48%) 0%, hsl(${hue2}, 60%, 38%) 100%)`,
                color: '#fff'
            };
        },

        goBackToList() {
            this.selectedConversation = null;
        },

        adjustChatHeight() {
            const el = document.getElementById('whatsapp-chat-app');
            if (!el) return;

            el.style.height = '300px';

            requestAnimationFrame(() => {
                const elRect = el.getBoundingClientRect();
                const footer = document.querySelector('footer.footer, .footer');

                let targetBottom;
                if (footer) {
                    const footerRect = footer.getBoundingClientRect();
                    targetBottom = footerRect.top;
                } else {
                    targetBottom = window.innerHeight;
                }

                const padding = 16;
                const available = targetBottom - elRect.top - padding;
                const minHeight = window.innerWidth <= 768 ? 360 : 480;
                const finalHeight = Math.max(minHeight, Math.floor(available));

                el.style.height = `${finalHeight}px`;
            });
        },
        
        openImage(url) {
            this.imageModalUrl = url;
            this.zoomLevel = 1; // Reset zoom
            this.panX = 0;
            this.panY = 0;
            this.isDragging = false;
        },
        
        closeImageModal() {
            this.imageModalUrl = null;
        },

        zoomIn() {
            this.zoomLevel += 0.25;
        },

        zoomOut() {
            if (this.zoomLevel > 0.5) {
                this.zoomLevel -= 0.25;
            }
        },

        startDrag(event) {
            if (this.zoomLevel <= 1) return; // Only pan if zoomed in
            event.preventDefault(); // Prevent default image drag
            
            this.isDragging = true;
            this.startX = event.clientX - this.panX;
            this.startY = event.clientY - this.panY;
        },

        onDrag(event) {
            if (!this.isDragging) return;
            
            event.preventDefault();
            this.panX = event.clientX - this.startX;
            this.panY = event.clientY - this.startY;
        },
        
        stopDrag() {
            this.isDragging = false;
        },

        wheelZoom(event) {
            if (event.deltaY < 0) {
                this.zoomIn();
            } else {
                this.zoomOut();
            }
        }
    },
    
    beforeDestroy() {
        this.stopPolling();
    }
});

// Solicitar permiso para notificaciones
if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
}
</script>
@endsection
