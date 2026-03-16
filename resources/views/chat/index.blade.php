@extends('layouts.app')

@section('title', 'Chat de WhatsApp')

@section('content')
<style>
    /* Reset & Base */
    #whatsapp-chat-app {
        font-family: 'Segoe UI', 'Helvetica Neue', Helvetica, Arial, sans-serif;
        height: calc(100vh - 100px); /* Adjust based on your layout header */
        display: flex;
        flex-direction: column;
        background-color: #f0f2f5;
        border: 1px solid #d1d7db;
        border-radius: 8px;
        overflow: hidden;
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
        background-color: #008069;
        color: white;
        padding: 10px 20px;
        display: flex;
        align-items: center; 
        justify-content: space-between;
        height: 60px;
    }
    
    .chat-toolbar h1 {
        font-size: 1.1rem;
        font-weight: 500;
        margin: 0;
    }

    .instance-selector select {
        padding: 5px 10px;
        border-radius: 4px;
        border: none;
        font-size: 0.9rem;
        color: #333;
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
        width: 350px;
        border-right: 1px solid #d1d7db;
        display: flex;
        flex-direction: column;
        background-color: #fff;
    }

    .search-box {
        padding: 10px;
        border-bottom: 1px solid #f0f2f5;
    }

    .search-input {
        width: 100%;
        padding: 8px 15px;
        background-color: #f0f2f5;
        border: none;
        border-radius: 8px;
        font-size: 0.95rem;
        outline: none;
    }

    .chat-list {
        flex: 1;
        overflow-y: auto;
    }

    .chat-item {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        cursor: pointer;
        border-bottom: 1px solid #f0f2f5;
        transition: background-color 0.2s;
    }

    .chat-item:hover {
        background-color: #f5f6f6;
    }

    .chat-item.active {
        background-color: #f0f2f5;
    }

    .avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background-color: #dfe3e5;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: #fff;
        font-size: 1.1rem;
        flex-shrink: 0;
        background: #00a884; /* Fallback/Default */
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
        background-color: #f0f2f5;
        border-bottom: 6px solid #25D366; 
    }

    .placeholder-icon svg {
        width: 80px;
        height: 80px;
        color: #aebac1;
    }

    /* Chat Header */
    .active-chat-header {
        height: 60px;
        background-color: #f0f2f5;
        padding: 0 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-left: 1px solid #d1d7db;
        border-bottom: 1px solid #d1d7db;
    }

    .header-info {
        display: flex;
        align-items: center;
    }
    
    .header-details {
        margin-left: 15px;
    }

    .header-details h3 {
        margin: 0;
        font-size: 1rem;
        font-weight: 500;
    }

    .header-details p {
        margin: 0;
        font-size: 0.8rem;
        color: #667781;
    }

    .btn-close {
        background: #ef4444; /* red-500 */
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 0.85rem;
        cursor: pointer;
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
        padding: 6px 7px 8px 9px;
        border-radius: 7.5px;
        position: relative;
        font-size: 0.9rem;
        line-height: 19px;
        box-shadow: 0 1px 0.5px rgba(0,0,0,0.13);
    }

    .message-bubble.outbound {
        background-color: #d9fdd3;
        border-top-right-radius: 0;
    }

    .message-bubble.inbound {
        background-color: #ffffff;
        border-top-left-radius: 0;
    }

    .message-content {
        margin-bottom: 5px;
        word-wrap: break-word;
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
        min-height: 62px;
        background-color: #f0f2f5;
        padding: 10px 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        border-top: 1px solid #d1d7db;
    }

    .btn-attach {
        color: #54656f;
        background: none;
        border: none;
        cursor: pointer;
        padding: 8px;
    }

    .input-wrapper {
        flex: 1;
        background-color: white;
        border-radius: 8px;
        padding: 9px 12px;
        display: flex;
        align-items: center;
    }

    .message-input {
        width: 100%;
        border: none;
        outline: none;
        font-size: 0.95rem;
        background: transparent;
    }

    .btn-send {
        background: none;
        border: none;
        cursor: pointer;
        padding: 8px;
        color: #54656f;
    }
    
    .btn-send.active {
        color: #008069;
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
    .custom-scroll::-webkit-scrollbar-thumb { background-color: rgba(0,0,0,0.2); border-radius: 3px; }

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
            
            <div style="display: flex; align-items: center; font-size: 0.8rem;">
                <span style="margin-right: 5px;">@{{ lastUpdate }}</span>
                <div :style="{
                    width: '8px', 
                    height: '8px', 
                    borderRadius: '50%',
                    backgroundColor: isPolling ? '#FFD700' : '#ccc'
                }"></div>
            </div>
        </div>
    </div>

    <!-- Main Layout -->
    <div v-if="selectedInstanceId" class="chat-layout">
        
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
                    <div class="avatar">
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
                        <div class="avatar" style="width: 40px; height: 40px;">
                            @{{ selectedConversation.initials }}
                        </div>
                        <div class="header-details">
                            <h3>@{{ selectedConversation.name }}</h3>
                            <p>@{{ selectedConversation.phone_number }}</p>
                        </div>
                    </div>
                    <div>
                        <button class="btn-close" @click="closeConversation">
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

                                    <!-- Badges de relaciones -->
                                    <div v-if="msg.contrato || msg.factura || msg.ingreso" class="message-relations" style="margin-top: 8px; display: flex; flex-wrap: wrap; gap: 6px;">
                                        <a v-if="msg.contrato" :href="msg.contrato.url" target="_blank" class="relation-badge badge-contrato" :title="'Contrato: ' + msg.contrato.nro">
                                            <i class="fas fa-file-contract" style="margin-right: 4px;"></i>
                                            Contrato: @{{ msg.contrato.nro }}
                                        </a>
                                        <a v-if="msg.factura" :href="msg.factura.url" target="_blank" class="relation-badge badge-factura" :title="'Factura: ' + msg.factura.codigo">
                                            <i class="fas fa-file-invoice-dollar" style="margin-right: 4px;"></i>
                                            Factura: @{{ msg.factura.codigo }}
                                        </a>
                                        <a v-if="msg.ingreso" :href="msg.ingreso.url" target="_blank" class="relation-badge badge-ingreso" :title="'Ingreso: ' + msg.ingreso.nro">
                                            <i class="fas fa-money-bill-wave" style="margin-right: 4px;"></i>
                                            Ingreso: @{{ msg.ingreso.nro }}
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

                    <div class="input-wrapper">
                        <input 
                            v-model="newMessage" 
                            @keyup.enter="sendMessage"
                            type="text" 
                            placeholder="Escribe un mensaje aquí" 
                            class="message-input"
                            :disabled="sending"
                        >
                    </div>

                    <button @click="sendMessage" class="btn-send" :class="{ 'active': newMessage.trim() }">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
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

        hasMoreConversations() {
            return this.currentPage < this.lastPage;
        },

        lastMessageError() {
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
    },

    beforeDestroy() {
        if (this.$refs.chatList && this._convScrollHandler) {
            this.$refs.chatList.removeEventListener('scroll', this._convScrollHandler);
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
            if (!this.newMessage.trim()) return;
            
            const message = this.newMessage;
            this.newMessage = '';
            this.sending = true;
            
            try {
                const response = await axios.post(
                    window.routes.send(this.selectedConversation.phone_number),
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
                alert('Error al enviar mensaje');
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
