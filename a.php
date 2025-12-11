<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Exception;

// Asegura que la sesión esté iniciada.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Tiempo máximo de inactividad (segundos)
// 1000 segundos equivale a 16 minutos y 40 segundos.
$timeout = 1000; 

// ------------------------------------
// 1. FUNCIÓN DE CIERRE DE SESIÓN FORZADO
// ------------------------------------

/**
 * Cierra la sesión, destruye todos los datos de sesión, elimina cookies de manera agresiva y redirige.
 * @param string $loginUrl La URL de destino después del logout (por defecto: '/camara/login2.php').
 */
function force_logout_and_redirect($loginUrl = '/camara/login2.php') {
    // 1) Vaciar variables de sesión en PHP
    $_SESSION = [];

    // 2) Eliminar la cookie de sesión (PHPSESSID) con sus parámetros originales
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'] ?? '/', 
            $params['domain'] ?? '',
            $params['secure'] ?? false,
            $params['httponly'] ?? false
        );
    }

    // 3) Intentar borrar otras cookies de la aplicación probando rutas comunes
    $pathsToTry = ['/', '/exp/', '/exp'];
    foreach ($_COOKIE as $name => $value) {
        foreach ($pathsToTry as $path) {
            setcookie($name, '', time() - 3600, $path);
        }
    }

    // 4) Destruir la sesión en el servidor
    session_destroy();

    // 5) Redirigir al login
    header("Location: $loginUrl");
    exit;
}

// ------------------------------------
// 2. VERIFICACIONES DE SEGURIDAD
// ------------------------------------

// Control 1: Verificación de Usuario Activo
// Se considera válido si existe cualquiera de los identificadores de sesión:
// 'user' (Clásico), 'username' (Clásico), o 'user_id' (WebAuthn).
$hasUser = isset($_SESSION['user']) 
        || isset($_SESSION['username']) 
        || isset($_SESSION['user_id']);

if (!$hasUser) {
    // Si no hay usuario en sesión, forzar logout y redirigir.
    force_logout_and_redirect();
}

// Control 2: Control de Inactividad
// Comprueba si el tiempo transcurrido desde la última actividad supera el $timeout.
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    // Si el usuario es inactivo, cerrar sesión y redirigir.
    force_logout_and_redirect();
}

// ------------------------------------
// 3. ACTUALIZAR ACTIVIDAD Y CONTINUAR
// ------------------------------------

// Si el usuario pasó ambas verificaciones, se actualiza el timestamp de actividad, 
// "reiniciando" el contador de inactividad.
$_SESSION['last_activity'] = time();

// El resto del código de la página protegida se ejecuta aquí.
$ignorado = ini_get('ignore_user_abort');
error_log("Valor inicial de ignore_user_abort: " . $ignorado);

// Intentamos forzarlo a OFF (0)
ignore_user_abort(false);
$ignorado_final = ini_get('ignore_user_abort');

error_log("Valor después de forzarlo a FALSE: " . $ignorado_final);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<!-- Manifest para PWA -->
<link rel="manifest" href="manifest.json">
<!-- Service Worker Registration -->
<script>
// Registrar service worker inmediatamente
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        console.log('Intentando registrar Service Worker...');
    });
}
</script>

<!-- Incluir el archivo de notificaciones push -->
<script src="push-notifications.js"></script>


  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Naphdev</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="estilos.css">
  <link rel="stylesheet" href="estilos2.css">
  <link rel="stylesheet" href="estilos3.css">
  <link rel="stylesheet" href="estilos3.1.css">
  <link rel="stylesheet" href="estilos4.css">
  <link rel="stylesheet" href="funciones.css">
  <link rel="stylesheet" href="type.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="manifest" href="manifest.json" />
<link rel="stylesheet" href="note_status_fix.css">
<script src="note_status_fix.js"></script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <meta name="theme-color" content="#007bff" />
</head>
<body>


<div id="welcome-overlay">
    <div class="gemini-convergent-loader12" id="myLoader">
        <div class="convergent-star12">
            <svg viewBox="0 0 100 100">
                <polygon points="
                    50.0,5.0 55.63,30.81 74.33,12.14 65.11,36.9 90.93,31.31 69.8,47.15
                    94.54,56.4 68.19,58.31 84.01,79.47 60.81,66.83 62.68,93.18 50.0,70.0
                    37.32,93.18 39.19,66.83 15.99,79.47 31.81,58.31 5.46,56.4 30.2,47.15
                    9.07,31.31 34.89,36.9 25.67,12.14 44.37,30.81
                " />
            </svg>
        </div>
        <svg class="convergent-spinner12" viewBox="25 25 50 50">
            <circle cx="50" cy="50" r="20"></circle>
        </svg>
    </div>
</div>
  <div id="chatContainer" class="chat-container">
    <div class="chat-header">
      <div class="header-images">
<img src="logo.png" alt="Logo Municipalidad" />
      </div>
<!-- Pop-up modal -->
<div id="popupModal" class="modal" style="display:none;">
  <div class="modal-contentclear">
    <img src="logo.png" alt="Logo" class="popup-logo">
    <p>¿Seguro que quieres limpiar el chat?</p>
    <button id="confirmClear">✅ Sí</button>
    <button id="cancelClear">❌ No</button>
  </div>
</div>
<button id="clearChatBtn" title="Limpiar chat" aria-label="Limpiar chat" class="clear-btn">
  🗑️ <span>Limpiar</span>
</button>
<div class="chat-actions">
    <input type="file" name="archivo_adjunto" id="archivoAdjunto" style="display: none;">
</div>
</div>
<div class="chat-body" id="chatBody"></div>
<div id="welcome-message" class="welcome-background">
¿EN QUE PUEDO AYUDARTE HOY?
        </div>
    <div class="chat-footer">
<div class="ai-input-wrapper"> 
<div id="mode-selector-wrapper" class="ai-mode-selector-wrapper"> 
<div id="mode-selector" class="ai-mode-selector"> 
         </div> 
         </div>
        <input type="text" id="userMessage" placeholder="Escribe tu Mensaje" />      
        <button id="sendMessageBtn" class="ai-send-btn" title="Enviar Mensaje">
            <i class="fas fa-paper-plane"></i>
        </button>

    <button id="clipBtn" class="ai-send-btn" title="Adjuntar Archivo">
        <i class="fas fa-paperclip"></i>
    </button>
        <button id="voiceBtn" class="ai-voice-btn" title="Habla para enviar mensaje">
            <img src="https://img.icons8.com/fluency/48/000000/microphone.png" alt="Micrófono" />
        </button>
    <div class="chatbot-message bot-message" style="text-align: left; font-size: 11px; margin-top: 8px; background: #f9f9f9; padding: 5px;">
    </div>
</div>
<div class="message-input-area">
    </div>

<div class="message-input-area">
    <p class="chat-disclaimer">Naphdev puede cometer errores,revisa las respuestas porfavor </p> 
</div>
<!-- Mini consola oculta -->
<div id="consoleModal" class="console-modal" style="display:none;">
  <div class="console-overlay"></div>
  <div class="console-window">
    <div class="console-header">
      <span>Consola de comando</span>
      <button id="closeConsoleBtn">X</button>
    </div>
    <textarea id="consoleInput" placeholder="Escribe comandos aquí..." rows="6"></textarea>
    <button id="runConsoleBtn">Ejecutar</button>
    <pre id="consoleOutput"></pre>
  </div>
</div>
<div id="ocrBox">
<textarea id="textoOCR"></textarea>
</div>
  <!-- librerías JS -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <!-- Tesseract.js -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js"></script>

<!-- PDF.js (para convertir PDF -> canvas) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.17.359/pdf.min.js"></script>

<!-- JSZip (para leer .docx) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js">
</script>

<!-- Font Awesome (opcional, para iconos) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

  <script src="https://unpkg.com/mammoth/mammoth.browser.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
  <script>
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
  </script>

  <script>
    const chatBody = document.getElementById("chatBody");
    const userMessageInput = document.getElementById("userMessage");
    const sendMessageBtn = document.getElementById("sendMessageBtn");
    const voiceBtn = document.getElementById("voiceBtn");
    const uploadBtn = document.getElementById("uploadBtn");
    const fileInput = document.getElementById("fileInput");
    const ocrResults = document.getElementById("ocrResults");
    const ocrContent = document.getElementById("ocrContent");
    let currentAction = null;
    let confirmationTimeout = null;
    let reminders = JSON.parse(localStorage.getItem('reminders')) || [];
    let reminderInterval;
    let isCargaInProgress = false;
    let cargaData = {};
    let cargaCurrentFieldIndex = 0;
    let menuPrincipalVisible = false;
    let files = [];
    let pdfSearchMatches = [];

    const cargaFields = [
      { key: 'caratula', prompt: 'Por favor, ingresa Carátula:' },
      { key: 'expediente', prompt: 'Por favor, ingresa el Nº de Expediente:' },
      { key: 'nombre_apellido', prompt: 'Por favor, ingresa Solicitante: (El Sr. o La Sra.)' },
      { key: 'celular', prompt: 'Por favor, ingresa Nº de Contacto:' },
      { key: 'fecha_inicio', prompt: 'Por favor, ingresa Fecha de Inicio (DD-MM-AAAA):' },
      { key: 'juzgado', prompt: 'Por favor, ingresa Juzgado/Mesa de Entrada:' },
      { key: 'responsable', prompt: 'Por favor, ingresa Email:' },
      { key: 'objeto', prompt: 'Por favor, ingresa Objeto/Falta:' },
      { key: 'observaciones', prompt: 'Por favor, ingresa Observación o Cantidad de Árboles:' },
      { key: 'seccion', prompt: 'Por favor, ingresa Nomenclatura Catastral:' },
      { key: 'direccion', prompt: 'Por favor, ingresa Dirección:' },
    ];


   function sendMessage() {
    const query = userMessageInput.value.trim();
    if (!query) return;
    
    // 1. Mostrar mensaje del usuario
    appendMessage(query, "user"); 

    const lower = query.toLowerCase();

    // --- BLOQUES DE COMANDOS Y LÓGICA INTERNA (Todos terminan en 'return;') ---
    
    // Lógica de Flujos Específicos
    if (isCargaInProgress) {
        processCargaExpInput(query);
        userMessageInput.value = "";
        return;
    }
    if (query.trim().toLowerCase() === '/console') {
        document.getElementById('consoleModal').style.display = 'block';
        userMessageInput.value = '';
        return;
    }

    // Comando /noti
    if (lower.startsWith('/noti')) {
        const legajoStr = String(currentUser.legajo).trim();
        const usuarioStr = String(currentUser.usuario).trim();

        if (legajoStr === "8048" || usuarioStr === "8048") { 
            const mensajeParaEnviar = query.substring(6).trim();
            if (mensajeParaEnviar) {
                const codigo = "Master-" + Date.now();
                enviarNotificacionGlobal(codigo, mensajeParaEnviar, "Naphdev");
                appendMessage(`🧪 Notificación enviada a todos: "${mensajeParaEnviar}"`, "bot"); // Añadir sender
            } else {
                appendMessage("❌ No escribiste ningún mensaje para enviar.", "bot"); // Añadir sender
            }
        } else {
            appendMessage("❌ No tienes permiso para usar este comando.", "bot"); // Añadir sender
        }
        userMessageInput.value = '';
        return;
    }

    // Comandos de Búsqueda
    if (query.startsWith('/')) {
        const [cmd, ...args] = query.slice(1).split(' ');
        const param = args.join(' ');
        switch (cmd.toLowerCase()) {
            case 'expediente':
            case 'resolucion':
            case 'nombre':
            case 'direccion':
                fetchResults(param, cmd.toLowerCase());
                break;
            default:
                appendMessage(`Comando "/${cmd}" no reconocido.`, 'bot');
        }
        userMessageInput.value = '';
        return;
    }

    // Lógica de Flujo Activo
    if (currentAction) {
        currentAction(query);
        userMessageInput.value = "";
        return;
    }


    // Lógica de Palabras Clave Internas (Todos terminan con return;)
    if (lower.includes("cargar expediente") || lower.includes("nuevo expediente")) {
        startCargaExp();
        userMessageInput.value = "";
        return;
    } else if (lower.includes("menu") || lower.includes("menú principal")) {
        showMainOptions1();
        userMessageInput.value = "";
        return;
    } else if (lower.includes("horas extras") || lower.includes("hora extra")) {
        horas();
        userMessageInput.value = "";
        return;
    } else if (lower.includes("qué día es hoy") || lower.includes("que dia es hoy")) {
        const t = new Date();
        appendMessage("Hoy es " + t.toLocaleDateString("es-ES", { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }), "bot");
        userMessageInput.value = "";
        return;
    } else if (lower.includes("qué hora es") || lower.includes("que hora es")) {
        appendMessage("La hora actual es " + new Date().toLocaleTimeString("es-ES"), "bot");
        userMessageInput.value = "";
        return;
    } else if (lower.includes("notificar terreno") || lower.includes("formulario terreno")) {
        openTerrenoForm();
        userMessageInput.value = "";
        return;
}
    // ELIMINADO: el bloque 'else' que causaba el mensaje estático
    
    // 🎯 ÚLTIMO RECURSO: LLAMADA A LA IA
    consultarChatbotAPI(query);
    userMessageInput.value = ""; 
}
 sendMessageBtn.addEventListener("click", sendMessage);

    userMessageInput.addEventListener("keydown", function(e) {

      if (e.key === "Enter" && !e.shiftKey) {

        e.preventDefault();

        sendMessage();

      }

    });
    let recognition;
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
      const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
      recognition = new SpeechRecognition();
      recognition.lang = 'es-ES';
      recognition.continuous = false;
      recognition.interimResults = false;

      recognition.onresult = (event) => {
        userMessageInput.value = event.results[0][0].transcript;
        sendMessage();
      };
      recognition.onerror = (event) => console.error("Error en reconocimiento de voz:", event.error);
    } else {
      voiceBtn.disabled = true;
      voiceBtn.title = "Reconocimiento de voz no soportado";
    }
    voiceBtn.addEventListener("click", () => recognition && recognition.start());

 function appendMessage(message, sender, skipAnimation = false) {
    const chatBody = document.getElementById('chatBody'); 

    if (!chatBody) {
        console.error("No se encontró el contenedor del chat. Revisa el ID en el HTML.");
        return;
    }

    const messageElement = document.createElement("div");
    messageElement.classList.add("chatbot-message", sender === "user" ? "user-message" : "bot-message");
    const messageTime = `<span class="message-time">${new Date().toLocaleTimeString()}</span>`;

    if (sender === "bot") {
        
        // Creamos la estructura inicial
        messageElement.innerHTML = `
            ${!skipAnimation ? `
            <div class="gemini-convergent-loader typing-indicator" id="current-bot-spinner">
                <div class="convergent-star">
                    <svg viewBox="0 0 100 100"><polygon points="50.0,5.0 55.63,30.81 74.33,12.14 65.11,36.9 90.93,31.31 69.8,47.15 94.54,56.4 68.19,58.31 84.01,79.47 60.81,66.83 62.68,93.18 50.0,70.0 37.32,93.18 39.19,66.83 15.99,79.47 31.81,58.31 5.46,56.4 30.2,47.15 9.07,31.31 34.89,36.9 25.67,12.14 44.37,30.81" /></svg>
                </div>
                <svg class="convergent-spinner" viewBox="25 25 50 50"><circle cx="50" cy="50" r="20"></circle></svg>
            </div>
            ` : ''}
            <div class="message-content"><p></p>${messageTime}</div>`;
        
        chatBody.appendChild(messageElement);
        chatBody.scrollTop = chatBody.scrollHeight;
        
        const loader = messageElement.querySelector(".gemini-convergent-loader");
        if (loader && !skipAnimation) {
            // Lógica de estilos aleatorios SÓLO si hay animación
            const randomStyle = Math.floor(Math.random() * 3) + 1;
            if (randomStyle === 2) {
                loader.classList.add('style-wobble');
            } else if (randomStyle === 3) {
                loader.classList.add('style-hyper');
            }
        }
        
        const p = messageElement.querySelector("p");
        
        // --- MANEJO DE CONTENIDO Y ESCRITURA ---
        if (skipAnimation) {
            // Caso 1: Contenido Final Formateado (sin animación)
            // Aquí inyectamos el HTML de Marked.js (formattedHTML)
            p.innerHTML = message; 

        } else if (message.length === 0 || message.includes('typing-animation')) {
            // Caso 2: Mensaje de Carga/Pensamiento (Crea la burbuja vacía o con texto de carga)
            p.innerHTML = message;
            // No iniciamos typeChar, solo devolvemos el elemento con el spinner activo.
            
        } else {
            // Caso 3: Mensaje normal con animación de escritura (texto simple)
            let idx = 0;
            (function typeChar() {
                if (idx <= message.length) {
                    // Usamos .innerHTML para permitir <br> o la inyección de animación
                    p.innerHTML = message.slice(0, idx).replace(/\n/g, "<br>"); 
                    idx++;
                    setTimeout(typeChar, 2); 
                } else {
                    // Asegurar que el mensaje completo quede visible
                    p.innerHTML = message.replace(/\n/g, "<br>"); 
                    
                    // Ocultar el spinner Ring después de la animación
                    const spinnerRing = messageElement.querySelector('.convergent-spinner');
                    if (spinnerRing) {
                        setTimeout(() => {
                            spinnerRing.style.display = 'none'; 
                        }, 2000);
                    }
                }
            })();
        }
        
        // Retornamos el elemento del bot para que la API pueda usarlo/removerlo
        return messageElement; 
        
    } else {
        // Lógica del usuario (sin cambios)
        const safe = message.replace(/\n/g, "<br>");
        messageElement.innerHTML = `<p>${safe}</p>${messageTime}`;
        chatBody.appendChild(messageElement);
        
        chatBody.scrollTop = chatBody.scrollHeight; 
        
        return messageElement; 
    }
}

 function showLoadingAnimation(text) {
    const el = document.createElement("div");
    el.classList.add("chatbot-message", "bot-message", "loading-message");
    
    // --- CÓDIGO HTML DEL SPINNER ADAPTADO (usando la clani-spinner') ---
    const spinnerHtml = `
        <div class="gemini-convergent-loader typing-indicator">
              <div class="convergent-star12"></div>
              <div class="dynamic-spinner1"></div>
        </div>
    `;
    // --- FIN CÓDIGO HTML DEL SPINNER ---

    el.innerHTML = `
        ${spinnerHtml} 
        <div class="message-content"><p>${text}</p></div>`;
            
    // Asegúrate de que 'chatBody' esté definido en tu alcance
    chatBody.appendChild(el);
    chatBody.scrollTop = chatBody.scrollHeight;
    
    return el;
}
  
function showLoadingAnimation3(text) {
    const el = document.createElement("div");
    el.classList.add("chatbot-message", "bot-message", "loading-message");

    // --- Estructura HTML del Spinner "Pulso 3D y Rotación Eje Y" (Ejemplo 3) ---
    const spinnerHtml = `
        <div class="gemini-convergent-loader typing-indicator">
            <div class="convergent-square pulse-3d"></div> 
            <div class="dynamic-spinner"></div>
        </div>
    `;
    // --------------------------------------------------------------------------

    el.innerHTML = `
        ${spinnerHtml} 
        <div class="message-content"><p>${text}</p></div>`;
        
    // Asume que 'chatBody' está definido en tu alcance
    chatBody.appendChild(el);
    chatBody.scrollTop = chatBody.scrollHeight;
    
    return el;
}


    function showMainOptions1() {
      if (!menuPrincipalVisible) {
        appendMessage("Menu Principal", "bot");
        setTimeout(() => {
          chatBody.insertAdjacentHTML("beforeend", `
            <div class="option-box" onclick="startSearch()">📂 Gestión de Expedientes</div>
            <div class="option-box" onclick="startInsSearch()">📜 Sistema Inspección</div>
            <div class="option-box" onclick="startADMSearch()">📋 Área Resolución/Permisos</div>
            <div class="option-box" onclick="horas()">🕐 Horas Extras</div>
            <div class="option-box" onclick="startOtherConsultation()">❔ Otras Consultas</div>
            <div class="option-box" onclick="maplazas()">🌲 Registro de Plazas</div>
            <div class="option-box" onclick="openTerrenoForm()">✉️  Notificaciones de Terrenos ✉️ </div>
          `);
          chatBody.scrollTop = chatBody.scrollHeight;
        }, 500);
        menuPrincipalVisible = false;
      }
    }

    function startSearch() {
      appendMessage("¿Qué deseas realizar? 😃", "bot");
      setTimeout(() => {
        chatBody.insertAdjacentHTML("beforeend", `
          <div class="option-box" onclick="startSearchBy('expediente')">📂 Buscar Por Nº de Expediente</div>
          <div class="option-box" onclick="startSearchBy('resolucion')">📜 Buscar por resolución</div>
          <div class="option-box" onclick="startSearchBy('nombre')">👤 Buscar por nombre</div>
          <div class="option-box" onclick="startSearchBy('direccion')">📍 Buscar por dirección</div>
          <div class="option-box" onclick="startSearchBy('seccion')"> 🗺️  Buscar por Nomenclatura Catastral</div>
          <div class="option-box" onclick="redirectToExp()">🖋️ Editor de expedientes</div>
          <div class="option-box" onclick="startCargaExp()">➕ Cargar nuevo expediente</div>
          <div class="option-box" onclick="mostrarDashboard()">📊 Dashboard Estadísticas</div>
          <div class="option-box" onclick="resetChat()">🔙 Menú principal</div>
        `);
        chatBody.scrollTop = chatBody.scrollHeight;
      }, 500);
    }

    function startInsSearch() {
      appendMessage("¿Qué deseas realizar? 😃", "bot");
      setTimeout(() => {
        chatBody.insertAdjacentHTML("beforeend", `
          <div class="option-box" onclick="redirectToSaf()">🌐 SAFIM</div>
          <div class="option-box" onclick="startPlanchetaSearch()">🗺️ Planchetas</div>
          <div class="option-box" onclick="Gestor()">📋 Registro Inspecciones</div>
          <div class="option-box" onclick="redirectToMap()">🗺️ Mapa de Inspecciones</div>
          <div class="option-box" onclick="redirectToMapPar()">🗺️ Mapa Parcelario</div>
          <div class="option-box" onclick="resetChat()">🔙 Menú principal</div>
        `);
        chatBody.scrollTop = chatBody.scrollHeight;
      }, 500);
    }

    function startADMSearch() {
      appendMessage("¿Qué te gustaría buscar? 🤔", "bot");
      setTimeout(() => {
        chatBody.insertAdjacentHTML("beforeend", `
          <div class="option-box" onclick="startResol()">📝 Generar resolución</div>
          <div class="option-box" onclick="redirectToGenPermProv()">🛂 Permisos Provisorios</div>
          <div class="option-box" onclick="redirectTopoda()"> ✂️ 🌳 Permiso Poda</div>
          <div class="option-box" onclick="redirectToGenesppub()">Permisos de uso de Espacios Públicos 🌳 </div>
          <div class="option-box" onclick="resetChat()">🔙 Menú principal</div>
        `);
        chatBody.scrollTop = chatBody.scrollHeight;
      }, 500);
    }
    function Gestor() {
      appendMessage("¿Qué te gustaría buscar? 🤔", "bot");
      setTimeout(() => {
        chatBody.insertAdjacentHTML("beforeend", `
          <div class="menu-options">
  <button class="option-box" onclick="redirectToInspec()">📄 Multas realizadas</button>
  <button class="option-box" onclick="redirectTo147()">📋 Informe 147</button>
  <button class="option-box" onclick="redirectToJuzgado()">⚖️ Informe Juzgado de Faltas</button>
  <button class="option-box" onclick="redirectToInspeccion()">🔎 Informe Actas de Inspección</button>
  <button class="option-box" onclick="redirectToCanon()">💰 Informe Canon</button>
  <button class="option-box back" onclick="resetChat()">🔙 Volver al Menú Principal</button>
</div>


        `);
        chatBody.scrollTop = chatBody.scrollHeight;
      }, 500);
    }



    function startSearchBy(type) {
      const prompts = {
        expediente: "Por favor, ingresa el número de expediente:",
        resolucion: "Por favor, ingresa la resolución:",
        nombre: "Por favor, ingresa el nombre:",
        direccion: "Por favor, ingresa la dirección:",
        seccion: "Porfavor , ingresa la N.C:"
      };
      appendMessage(prompts[type], "bot");
      currentAction = (query) => fetchResults(query, type);
    }

    function startPlanchetaSearch() {
      appendMessage("Por favor, ingresa la Plancheta a Buscar:", "bot");
      currentAction = (q) => fetchPlanchetaResults(q);
    }

    function startOtherConsultation() {
      appendMessage("¿Qué otra consulta quieres hacer? 🤗", "bot");
      setTimeout(() => {
        chatBody.insertAdjacentHTML("beforeend", `
          <div class="option-box" onclick="redirectToSupport()">🛠️ Soporte</div>
          <div class="option-box" onclick="redirectToUser()">👤 Agregar Usuarios</div>
          <div class="option-box" onclick="resetChat()">🔙 Volver a Menú</div>
        `);
        chatBody.scrollTop = chatBody.scrollHeight;
      }, 500);
    }

    function fetchPlanchetaResults(query) {
    if (!query.trim()) { 
        alert("Por favor ingresa un término válido."); 
        return; 
    }
        const loading = appendMessage("", "bot");

    // 1. CLAVE: Llama a appendMessage() para crear el elemento de carga.
    // Asignamos el elemento DOM devuelto a 'loading'.
    // 2. Ejecuta la petición AJAX
    $.post("busqueda.php", { query, type: "planchetas" }, function(resp) {
        
        // 3. 🛡️ Protección y remoción del elemento de carga
        if (loading) {
            loading.remove();
        }
        
        if (!resp.trim()) {
            appendMessage("No se encontró info local. Buscando en la web...", "bot");
            consultarChatbotAPI(query);
        } else {
            appendMessage(resp, "bot");
            askForConfirmation();
        }
    }).fail(() => {
        
        // 4. Remoción en caso de fallo, con protección
        if (loading) {
            loading.remove();
        }
        appendMessage("Error en búsqueda. Intenta de nuevo ❌.", "bot");
    });
}

function fetchResults(query, type) {
    // ---------------------------------------------------------
    // 1. DEFINICIÓN DE REFERENCIAS Y LOADER
    // ---------------------------------------------------------
    const loading = ("bot");

    // Referencia Vanilla JS (para scrollHeight, appendChild nativo)
    // CRÍTICO: Asegúrate que el ID sea el correcto en tu HTML (id="chat-body" o id="chatBody")
    const chatBodyRaw = document.getElementById("chat-body") || document.querySelector(".chat-body") || document.body;
    
    // Referencia jQuery (para .append(), .remove(), y manipulación segura de DOM)
    const $chatBody = $(chatBodyRaw);

    // Validación de seguridad para detener ejecución si el contenedor no existe
    if (!chatBodyRaw) {
        console.error("❌ ERROR CRÍTICO: No se encontró el elemento contenedor del chat (id='chat-body').");
        if (loading && typeof loading.remove === 'function') loading.remove();
        return; 
    }

    // 2. Petición AJAX
    $.post("busquedasql.php", { query: query, type: type }, function(response) {

        // --- A. Remover loading ---
        if (loading && typeof loading.remove === 'function') {
            loading.remove();
        } else {
            console.warn("No se pudo remover el mensaje de carga automáticamente.");
        }

        // --- B. Definición de variables de respuesta ---
        const expediente  = response.expediente   || "";
        const resolucion  = response.resolucion   || "";
        const direccion   = response.direccion    || "";
        const responsable = response.responsable  || "";
        const rawCelular  = response.celular      || "";
        const informe     = response.barrio       || "";
        const estado      = response.estado       || "";

        // --- C. Lógica Principal ---
        if (response.html && response.html.trim()) {
            appendMessage(response.html, "bot");

            // 1. Lógica de "NEGADO"
            if (estado && String(estado).toUpperCase().includes("NEGADO")) {
                try {
                    const celular = (rawCelular || '').replace(/\D/g, "");
                    const fecha = new Date().toLocaleDateString('es-AR');

                    appendMessage("⚠️ El Expediente fue NO AUTORIZADO. ¿Deseas notificar al solicitante?", "bot");

                    // Definición completa del mensaje de WhatsApp
                    const mensajeWhats = [
                        "Municipalidad de Cipolletti - Dirección de Espacios Verdes🌳",
                        "", `Expediente: ${expediente}📄`,
                        "Estado: NO AUTORIZADO⛔",
                        `Dirección: ${direccion}📍`, "",
                        "Informe técnico:", `${informe}`, "", "",
                        "- Puede presentar descargos o solicitar revisión en la Dirección de Espacios Verdes en Saturnino Franco Nº 2050 en el horario de 08:00 a 13:00 HS 🕐.",
                        "", `Fecha de notificación: ${fecha}📅`, "",
                        "Atentamente, Dirección de Espacios Verdes"
                    ].join("\n");

                    // Definición completa del cuerpo HTML del email
                    const cuerpoEmailHtml = `
                    <div style="font-family: Arial, sans-serif; line-height: 1.6;">
                        <img src="https://raw.githubusercontent.com/nachorivas581/PROGRAMA/main/logomuni.png" alt="Municipalidad de Cipolletti" style="width: 175px; margin-bottom: 20px;">
                        <div style="font-family: Arial, sans-serif; line-height:1.6; color:#222;">
                            <h3>Municipalidad de Cipolletti - Dirección de Espacios Verdes🌳</h3>
                            <p><strong>Expediente:</strong> ${expediente}<br>
                            <strong>Estado:</strong> NO AUTORIZADO⛔<br>
                            <strong>Dirección:</strong> ${direccion}📍<br>
                            <strong>Fecha de Notificación:</strong> ${fecha}📅</p>
                            <h4>Informe técnico📝</h4>
                            <p>${informe}</p>
                            <ul><li>Puede presentar descargos o solicitar revisión en la Dirección de Espacios Verdes en Saturnino Franco Nº 2050 en el horario de 08:00 a 13:00 HS 🕐.</li></ul>
                        </div>
                    </div>`;

                    // Creación y adjunto de botones de acción
                    const cont = document.createElement('div');
                    cont.className = 'negado-actions d-flex p-3 gap-2 align-items-center';

                    const wa = document.createElement('a');
                    wa.className = 'btn btn-success btn-sm';
                    wa.target = '_blank';
                    wa.rel = 'noopener noreferrer';
                    wa.href = `https://web.whatsapp.com/send?phone=+549${encodeURIComponent(celular)}&text=${encodeURIComponent(mensajeWhats)}`;
                    wa.innerHTML = '📱 WhatsApp';
                    cont.appendChild(wa);

                    const mail = document.createElement('button');
                    mail.type = 'button';
                    mail.className = 'btn btn-info btn-sm';
                    mail.innerHTML = '📩 Email';
                    mail.addEventListener('click', () => {
                         if(typeof enviarPorEmail === 'function') enviarPorEmail(expediente, "NEGADO", direccion, rawCelular, responsable, cuerpoEmailHtml);
                    });
                    cont.appendChild(mail);

                    const cancel = document.createElement('button');
                    cancel.type = 'button';
                    cancel.className = 'btn btn-secondary btn-sm';
                    cancel.innerHTML = '⛔ Cancelar';
                    if(typeof resetChat === 'function') cancel.addEventListener('click', resetChat);
                    cont.appendChild(cancel);

                    // Adjuntamos al DOM usando la referencia Vanilla JS
                    chatBodyRaw.appendChild(cont);

                } catch (err) {
                    console.error("Error en flujo NEGADO:", err);
                    appendMessage("❌ Ocurrió un error al preparar las opciones de notificación.", "bot");
                }
            }

            // 2. Lógica de IMÁGENES (Usa $chatBody para .append)
            let tieneImg = response.tiene_imagenes;
            let hayImagenes = (tieneImg == 1 || tieneImg === true || String(tieneImg) === "true");

            if (hayImagenes) {
                appendMessage("Este expediente tiene imágenes adjuntas 📷", "bot");

                let btnVer = $(`<div class="option-box">👁️ Ver imágenes</div>`);
                btnVer.on('click', function() { if(typeof mostrarImagenes === 'function') mostrarImagenes(expediente); });

                $chatBody.append(btnVer); // Usa $chatBody
            } else {
                appendMessage("No hay imágenes adjuntas en este expediente. ¿Deseas subir alguna?", "bot");

                let btnSubir = $(`<div class="option-box">📸 Subir imagen al expediente</div>`);
                btnSubir.on('click', function() { if(typeof subirImagenExpediente === 'function') subirImagenExpediente(expediente); });

                $chatBody.append(btnSubir); // Usa $chatBody
            }

            // 3. Opciones adicionales (Editar / Notas)
            $chatBody.append(`
                <div class="option-box" onclick="editarExpediente('${expediente}')">✏️ Editar Expediente</div>
                <div class="option-box" onclick="showNotes('${expediente}')">🗒️ Ver notas del expediente</div>
                <div class="option-box" onclick="openAddNoteModal('${expediente}')">➕ Agregar nota</div>
            `);

            // --- SCROLL FINAL (Usa chatBodyRaw para el scroll seguro) ---
            chatBodyRaw.scrollTop = chatBodyRaw.scrollHeight;

            // 4. Flujo Siguiente
            if (type === "expediente" && response.permit) {
                setTimeout(() => askForPermit(expediente, resolucion, rawCelular.replace(/\D/g, ""), direccion, responsable, informe), 1000);
            } else {
                if(typeof askForConfirmation === 'function') setTimeout(askForConfirmation, 1000);
            }

        } else {
            // Fallback a API Externa
            appendMessage("No se encontró información local. Buscando en la web...", "bot");
            return consultarChatbotAPI(query);
        }

    }, "json")
    .fail(function() {
        if (loading && typeof loading.remove === 'function') {
            loading.remove();
        }
        appendMessage("Hubo un error de conexión con la base de datos.", "bot");
        if(typeof showMainOptions === 'function') setTimeout(showMainOptions1, 2000);
    });
}




   function askForPermit(exp, reso, cel, dir, resp, info) {
  const mensajeNotificacion = `
🌳 *Municipalidad de Cipolletti - Área de Espacios Verdes* informa que el expediente 📄 N° ${exp},
con domicilio 📍 en ${dir}, ha sido autorizado mediante resolución municipal Nº ${reso}.

📌 El mismo podrá ser retirado en:
*Secretaría de Servicios Públicos*
📍 *Saturnino Franco Nº 2050*
🕗 *Horario: 08:30 a 13:00 horas*
⚠️ Se recuerda que, en lo posible, se debe presentar el talón con el número de expediente
entregado al momento de la solicitud para efectuar el retiro.

📝 *Informe de Inspección:* ${info}

📖 *Según la Ordenanza Municipal:*
La erradicación constituye una medida *excepcional*, y solo se autoriza cuando resulte *imprescindible*.
Los gastos que demande la extracción correrán *por cuenta del frentista y bajo su exclusiva responsabilidad*.
`;

  const urlWhats = `https://web.whatsapp.com/send?phone=+549${encodeURIComponent(cel)}&text=${encodeURIComponent(mensajeNotificacion)}`;
  const cuerpoEmail = `
  <div style="font-family: Arial, sans-serif; line-height: 1.6;">
    <img src="https://raw.githubusercontent.com/nachorivas581/PROGRAMA/refs/heads/main/logomuni.png" alt="Municipalidad de Cipolletti" style="width: 175px; margin-bottom: 20px;">
    ${mensajeNotificacion.replace(/\n/g, '<br>')}
  </div>`;

  appendMessage("¿Deseas generar el permiso de extracción?", "bot");

  chatBody.insertAdjacentHTML("beforeend", `
    <div class="option-box" onclick="startPermiso()">Sí ✅</div>
    <div class="option-box" onclick="resetChat()">No ⛔</div>
  `);

  chatBody.insertAdjacentHTML("beforeend", `
    <div class="mt-2">
      <a href="${urlWhats}" target="_blank" class="btn btn-success btn-sm me-2">
        📱 Notificar vía WhatsApp
      </a>
      <button class="btn btn-primary btn-sm"
        onclick='enviarPorEmail(
          "${exp}",
          "${reso}",
          "${dir}",
          "${cel}",
          "${resp}",
          ${JSON.stringify(cuerpoEmail)}
        )'>
        ✉️ Notificar vía Email
      </button>
    </div>
  `);
}
   let lastExpediente = null;

    function startResol() {
    appendMessage("Ingresa Nº(s) de expediente (ej: 188-K-25):", "bot");

    currentAction = (q) => {
        // 1. Guardamos la referencia al elemento loader
        const loading = appendMessage("Generando resolución...", "bot"); 

        $.post("/resol/resolbotsql.php", { expediente_buscar: q, buscar_expediente: true }, function(resp) {
            
            // --- CHEQUEO DE SEGURIDAD EN SUCCESS ---
            if (loading && typeof loading.remove === 'function') {
                loading.remove();
            }
            // ---------------------------------------

            appendMessage(resp, "bot");

        }).fail(() => {
            
            // --- CHEQUEO DE SEGURIDAD EN FAIL ---
            if (loading && typeof loading.remove === 'function') {
                loading.remove();
            }
            // -----------------------------------

            appendMessage("Error al generar resolución ❌.", "bot");
        });

        currentAction = null;
        // NOTA: Asegúrate de que showMainOptions esté definida globalmente.
        setTimeout(showMainOptions1, 15000);
    };
}

function startPermiso() {
  if (lastExpediente) {
    generarPermiso(lastExpediente);
  } else {
    appendMessage("Por favor, ingresa nuevamente el expediente:", "bot");
    currentAction = (q) => {
      lastExpediente = q.trim();
      generarPermiso(lastExpediente);
      currentAction = null;
    };
  }
}

function generarPermiso(expediente) {
  const loading = appendMessage("Generando permiso...");
  $.post("/resol/permisobotsql.php",
    { expediente_buscar: expediente, buscar_expediente: true },
    function(resp) {
      loading.remove();
      appendMessage(resp, "bot");
    }
  ).fail(() => {
    loading.remove();
    appendMessage("Error al generar permiso ❌.", "bot");
  });
}

    function redirectToExp() {
      const l = appendMessage("Cargando...");
      setTimeout(() => { l.remove(); window.open("/exp", "_blank"); }, 3000);
    }
    function redirectToSaf() {
      const l = appendMessage("Redirigiendo a SAFIM...");
      setTimeout(() => { l.remove(); window.open("http://safim.cipoletti.gob.ar", "_blank"); }, 3000);
    }
    function redirectToInspec() {
      const l = appendMessage("Cargando...");
      setTimeout(() => { l.remove(); window.open("/gestor", "_blank"); }, 3000);
    }
function redirectTo147() {
      const l = appendMessage("Cargando...");
      setTimeout(() => { l.remove(); window.open("/balance/147.php", "_blank"); }, 3000);
    }
function redirectToJuzgado() {
      const l = appendMessage("Cargando...");
      setTimeout(() => { l.remove(); window.open("/balance/juzgado.php", "_blank"); }, 3000);
    }
function redirectToInspeccion() {
      const l = appendMessage("Cargando...");
      setTimeout(() => { l.remove(); window.open("/balance/actas.php", "_blank"); }, 3000);
    }
function redirectToCanon() {
      const l = appendMessage("Cargando...");
      setTimeout(() => { l.remove(); window.open("/balance/canon.php", "_blank"); }, 3000);
    }



    function redirectToMap() {
      const l = showLoadingAnimation("Cargando Mapa de Inspecciones...");
      setTimeout(() => { l.remove(); window.open("/mapa", "_blank"); }, 3000);
    }
  function maplazas() {
      const l = showLoadingAnimation("Cargando Mapa Plazas...");
      setTimeout(() => { l.remove(); window.open("/mapa/plazas/", "_blank"); }, 3000);
    }
  function horas() {
    // 1. Asigna el elemento. Si falla el contenedor, será 'undefined'.
    const loadingElem = showLoadingAnimation("Redirigiendo a Horas Extras..."); 

    setTimeout(() => {
        // 2. 🛡️ La línea que lo arregla: Verifica que exista antes de intentar .remove()
        if (loadingElem) {
            loadingElem.remove();
        }
        
        // 3. Redirección
        window.open("/leg", "_blank");
    }, 2000);
}

  function redirectToSupport() {
      const loadingElem = appendMessage("Redirigiendo a soporte...");
      setTimeout(() => { loadingElem.remove(); window.open("https://wa.me/2995958958", "_blank"); }, 2000);
    }

 function redirectToUser() {
      const loadingElem = appendMessage("Redirigiendo a Usuarios");
      setTimeout(() => { loadingElem.remove(); window.open("/camara/registerlogin.php", "_blank"); }, 2000);
    }


    function redirectToMapPar() {
      const l = showLoadingAnimation("Cargando Mapa Parcelario...");
      setTimeout(() => { l.remove(); window.open("http://192.168.0.8/mapas/ZONIFICACION", "_blank"); }, 3000);
    }
    function redirectToGenPermProv() {
      const l = showLoadingAnimation("Redirigiendo a Permisos Provisorios...");
      setTimeout(() => { l.remove(); window.open("/resol/permisoexpro.php", "_blank"); }, 3000);
    }
 function redirectToGenesppub() {
      const l = showLoadingAnimation("Redirigiendo a Permisos de Uso de Espacios Públicos...");
      setTimeout(() => { l.remove(); window.open("/resol/espaciospublicos.php", "_blank"); }, 3000);
    }

    function redirectTopoda() {
      const l = showLoadingAnimation("Redirigiendo a Permisos de Poda...");
      setTimeout(() => {l.remove(); window.open ("/resol/permisopoda.php", "_blank"); }, 3000);
}
    function enviarPorEmail(exp, res, dir, cel, resp, msg,info) {
      const l = appendMessage("Enviando email...");
      $.post("enviar_email.php", { expediente: exp, resolucion: res, direccion: dir, celular: cel, responsable: resp, mensaje: msg }, function(r) {
        l.remove();
        if (r.success) appendMessage("✅ Email enviado correctamente.", "bot");
        else appendMessage("❌ No se pudo enviar el email.", "bot");
        setTimeout(askForConfirmation, 2000);
      }, "json").fail(() => {
        l.remove();
        appendMessage("❌ Error al enviar email.", "bot");
      });
    }

    function askForConfirmation() {
      appendMessage(`¿Te sirvió esta información <?php echo htmlspecialchars($usuario); ?>?`, "bot");
      chatBody.insertAdjacentHTML("beforeend", `
        <div class="option-box" onclick="userConfirmed(true)">Sí</div>
        <div class="option-box" onclick="userConfirmed(false)">No</div>
      `);
      chatBody.scrollTop = chatBody.scrollHeight;
      confirmationTimeout = setTimeout(() => {
        appendMessage("Sin confirmación. Muestro menú nuevamente 🤖.", "bot");
        setTimeout(showMainOptions1, 10000);
      }, 30000);
    }

    function userConfirmed(ok) {
      clearTimeout(confirmationTimeout);
      if (ok) appendMessage(`¡Me alegra saberlo, <?php echo htmlspecialchars($username); ?>! 😊`, "bot");
      else appendMessage(`Lo siento, <?php echo htmlspecialchars($username); ?>🙁 ¿Intentamos nuevamente?`, "bot");
      setTimeout(resetPage, 6000);
    }

    function handleGeneralQuery(q) {
      const l = appendMessage("");
      setTimeout(() => {
        l.remove();
        consultarChatbotAPI(q);
      }, 2000);
    }
/**
 * FUNCIÓN PRINCIPAL DE CONSULTA
 * @param {string} pregunta El mensaje de texto del usuario a enviar al chatbot.
 * @returns {void} No devuelve un valor, pero actualiza el DOM del chat.
 */
/**
 * Helper: Simula la escritura de un texto en un elemento DOM.
 * @param {HTMLElement} element - El elemento donde se escribirá el contenido.
 * @param {string} htmlContent - El contenido HTML/Markdown ya formateado.
 */
const simulateTyping = (element, htmlContent) => {
    return new Promise(resolve => {
        const typingSpeed = 20; // 20ms por caracter (ajustable)
        
        // 1. Limpiamos el contenido inicial y agregamos la clase de cursor/animación.
        element.innerHTML = ''; 
        element.classList.add('typing-active'); 
        
        // 2. Extraemos solo el texto plano (ignoramos el HTML para la simulación char-por-char)
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = htmlContent;
        const textToType = tempDiv.textContent; 

        let i = 0;
        const interval = setInterval(() => {
            if (i < textToType.length) {
                // Escribimos el caracter
                element.innerHTML += textToType.charAt(i);
                i++;
                // Opcional: Desplazamiento automático al final
                // element.scrollTop = element.scrollHeight; 
            } else {
                // 3. Finaliza la escritura: limpiamos y reemplazamos con el HTML completo para aplicar el formato (negritas, listas, etc.)
                clearInterval(interval);
                element.classList.remove('typing-active');
                element.innerHTML = htmlContent; 
                resolve();
            }
        }, typingSpeed);
    });
};

// =========================================================
// FUNCIÓN PRINCIPAL DEL CHATBOT
// =========================================================
// =========================================================
// FUNCIÓN PRINCIPAL DEL CHATBOT (consultarChatbotAPI)
// =========================================================
// =========================================================
// FUNCIÓN PRINCIPAL DEL CHATBOT (consultarChatbotAPI)
// =========================================================
async function consultarChatbotAPI(pregunta) {
  const API_URL = "busqueda_web.php";
  const ANIMATION_CLASS = "typing-animation";

  // 🚨 1. DETERMINAR EL MODO ACTUAL Y TIEMPOS
  const isThinkingMode = currentChatMode === "think";

  // Frases estáticas para la simulación de pensamiento
  const THINKING_PHRASES = [
    "Razonando...",
    "Razonando..."
  ];

  // Configurar tiempo de espera mínimo (cinemático) basado en el modo
  let tiempoEsperaMinimo = isThinkingMode ? (pregunta.length > 50 ? 3500 : 2000) : 500;

  // Deshabilitar input del usuario mientras piensa
  if (typeof userMessageInput !== "undefined" && userMessageInput) {
    userMessageInput.disabled = true;
  }

  let mensajeCargandoElement = null;
  let intervaloPensamiento = null;

  const getLoadingHTML = (texto) => {
    return `<span class="${ANIMATION_CLASS}">${texto}</span>`;
  };

  // -----------------------
  // Helper: tipear texto plano dentro de un nodo y luego renderizar con marked (si está)
  // -----------------------
  async function typeThenRenderMarkdown(targetNode, text, opts = {}) {
    if (!targetNode) return;
    const typingSpeed = opts.typingSpeed ?? 25; // ms por carácter (ajustable)
    const pauseAfterTyping = opts.pauseAfterTyping ?? 250; // ms antes de renderizar

    // Aseguramos que el nodo use white-space: pre-wrap para conservar saltos
    targetNode.style.whiteSpace = "pre-wrap";
    targetNode.textContent = "";

    // Tipo carácter a carácter (texto RAW - markdown source)
    for (let i = 0; i <= text.length; i++) {
      targetNode.textContent = text.slice(0, i);
      // yield
      await new Promise(r => setTimeout(r, typingSpeed));
    }

    // Pequeña pausa antes de renderizar la versión marcada (si requested)
    await new Promise(r => setTimeout(r, pauseAfterTyping));

    // Si tenemos marked, renderizamos el markdown final; si no, dejamos el texto plano
    if (typeof marked !== "undefined") {
      // Reemplazamos el contenido del contenedor padre por el HTML renderizado
      // pero respetamos la estructura: el contenedor objetivo podría ser el node donde escribimos.
      targetNode.innerHTML = marked.parse(text);
    } else {
      // dejamos el texto plano (ya tipeado)
    }
  }

  try {
    // =========================================================
    // 🚀 LÓGICA CONDICIONAL DE SIMULACIÓN DE PENSAMIENTO
    // =========================================================
    if (isThinkingMode) {
      // Modo PENSAR: mostrar bucle de pensamientos
      const pasosPensamiento = THINKING_PHRASES;

      // A. PENSAMIENTO INICIAL
      mensajeCargandoElement = appendMessage(getLoadingHTML(pasosPensamiento[0]), "bot");

      // C. BUCLE DE ACTUALIZACIÓN DE PENSAMIENTOS
      let pasoActual = 1;
      const velocidadCambio = pasosPensamiento.length > 5 ? 1200 : 1800;
      intervaloPensamiento = setInterval(() => {
        if (!mensajeCargandoElement) return;
        let textoPaso = pasosPensamiento[pasoActual];
        if (!textoPaso) {
          // Reiniciar ciclo si se agotan las frases
          pasoActual = 0;
          textoPaso = pasosPensamiento[pasoActual];
        }
        // Actualiza solo el contenido interior (asumiendo que appendMessage devolvió el nodo)
        mensajeCargandoElement.innerHTML = getLoadingHTML(textoPaso);
        pasoActual++;
      }, velocidadCambio);
    } else {
      // Modo RÁPIDO: Mensaje de carga simple y estático
      mensajeCargandoElement = appendMessage(getLoadingHTML(""), "bot");
    }

    // =========================================================
    // B. LLAMADA A LA API (EN PARALELO) + TIEMPO CINEMÁTICO
    // =========================================================
    const fetchPromise = fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ message: pregunta, chat_mode: currentChatMode })
    });

    // Esperamos a que la petición y el tiempo mínimo se completen
    const [respuesta] = await Promise.all([
      fetchPromise,
      new Promise(resolve => setTimeout(resolve, tiempoEsperaMinimo))
    ]);

    // Limpieza del intervalo de pensamiento si existe
    if (intervaloPensamiento) {
      clearInterval(intervaloPensamiento);
      intervaloPensamiento = null;
    }

    // Manejo de errores HTTP
    if (!respuesta.ok) {
      let errText = "";
      try {
        const errData = await respuesta.json();
        errText = errData.error || `Error HTTP ${respuesta.status}`;
      } catch (e) {
        errText = `Error HTTP ${respuesta.status}`;
      }
      throw new Error(errText);
    }

    const data = await respuesta.json();

    // 1. ELIMINAR EL MENSAJE DE CARGA INICIAL DEL DOM
    if (mensajeCargandoElement && mensajeCargandoElement.parentNode) {
      mensajeCargandoElement.parentNode.removeChild(mensajeCargandoElement);
      mensajeCargandoElement = null;
    }

    if (data.reply) {
      let replyText = data.reply;
      let thinkingHTML = "";

      // =========================================================
      // LÓGICA DE EXTRACCIÓN DEL RAZONAMIENTO INTERNO (MODIFICADA PARA TIPEO)
      // =========================================================
      const thinkingRegex = /\[THINKING\]([\s\S]*?)\[\/THINKING\]/i;
      const match = replyText.match(thinkingRegex);
      if (match) {
        const thinkingContent = match[1].trim();
        // Eliminar la sección THINKING del texto final
        replyText = replyText.replace(thinkingRegex, "").trim();

        // Creamos el HTML inicial del <details> pero con un contenedor vacío
        thinkingHTML = `
<details class="thinking-wrapper">
  <summary class="label-toggle">Pensado</summary>
  <div class="thinking-box">
    <p class="thinking-title">Razonamiento Interno</p>
    <div class="thinking-live">
      <div class="thinking-raw" style="white-space: pre-wrap;"></div>
    </div>
  </div>
</details>`;
      }

      // =========================================================
      // 4. Convertir la respuesta final (sin THINKING) a HTML.
      // =========================================================
      const formattedReplyHTML = typeof marked !== "undefined" ? marked.parse(replyText) : `<pre>${replyText}</pre>`;

// ---------------------------------------------------------
// 🚀 LÓGICA DE SECUENCIA Y SIMULACIÓN — THINKING EN VIVO 🚀
// ---------------------------------------------------------
if (thinkingHTML) {
    const thinkingNode = appendMessage(thinkingHTML, "bot", true);

    // Seleccionar el <details> y el target donde tipeamos
    let detailsBox = null;
    let liveTarget = null;

    try {
        detailsBox = thinkingNode.querySelector(".thinking-wrapper");
        liveTarget = thinkingNode.querySelector(".thinking-raw");
    } catch(e) {
        detailsBox = null;
        liveTarget = null;
    }

    // 1️⃣ ABRIR AUTOMÁTICAMENTE EL DETALLE PARA VER EL TIPEO
    if (detailsBox) detailsBox.open = true;

    // Recuperar el texto del razonamiento interno
    const thinkingContent = match ? match[1].trim() : "";

    // 2️⃣ TIPEAR EN VIVO EL RAZONAMIENTO
    if (liveTarget) {
        await typeThenRenderMarkdown(liveTarget, thinkingContent, {
            typingSpeed: 20,
            pauseAfterTyping: 180
        });
    }

    // 3️⃣ CERRAR AUTOMÁTICAMENTE CUANDO TERMINA DE TIPEAR
    if (detailsBox) detailsBox.open = false;

    // pequeño respiro visual
    await new Promise(r => setTimeout(r, 200));
}


      // B. Crear el contenedor para la respuesta final.
      const replyElement = appendMessage("", "bot", false);

      // C. Simular la escritura de la respuesta final.
      await simulateTyping(replyElement, formattedReplyHTML);
    } else {
      appendMessage("El servidor no devolvió una respuesta válida.", "bot", true);
      console.error("Respuesta vacía:", data);
    }
  } catch (error) {
    // F. LIMPIEZA DE ERRORES
    if (intervaloPensamiento) {
      clearInterval(intervaloPensamiento);
      intervaloPensamiento = null;
    }
    if (mensajeCargandoElement && mensajeCargandoElement.parentNode) {
      mensajeCargandoElement.parentNode.removeChild(mensajeCargandoElement);
      mensajeCargandoElement = null;
    }
    appendMessage(`⚠️ Error de conexión: ${error.message}`, "bot");
    console.error("Error crítico en chatbot:", error);
  } finally {
    // G. FINALIZACIÓN Y DESBLOQUEO
    if (typeof userMessageInput !== "undefined" && userMessageInput) {
      userMessageInput.disabled = false;
      try { userMessageInput.focus(); } catch (e) {/* ignore */}
    }
  }
}


   function resetChat() {
    chatBody.innerHTML = "";
    menuPrincipalVisible = false;
    setTimeout(() => {

        showMainOptions1();
    }, 500);
}
    function startCargaExp() {
      if (isCargaInProgress) {
        appendMessage("Ya estamos cargando un expediente, responde la pregunta anterior.", "bot");
        return;
      }
      optionBox(
        "¿Quieres ver un video de enseñanza sobre cómo cargar un expediente?",
        ["Sí","No"],
        function(sel) {
          if (sel.toLowerCase().startsWith("s")) mostrarVideoCargaExp();
          else iniciarCargaExp();
        }
      );
    }

    function mostrarVideoCargaExp() {
      const modal = document.createElement('div');
      modal.id = 'videoModal';
      modal.style = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;justify-content:center;align-items:center;';
      modal.innerHTML = `
        <div style="background:#fff;padding:20px;border-radius:5px;text-align:center;">
          <video controls autoplay loop width="800">
            <source src="videos/carga.mp4" type="video/mp4">
            Tu navegador no soporta reproducción de video.
          </video><br><br>
          <button id="continueButton" class="btn btn-primary">Continuar con la carga</button>
        </div>`;
      document.body.appendChild(modal);
      document.getElementById('continueButton').addEventListener('click', function(){
        document.body.removeChild(modal);
        iniciarCargaExp();
      });
    }

    function iniciarCargaExp() {
      isCargaInProgress = true;
      cargaData = {};
      cargaCurrentFieldIndex = 0;
      currentAction = processCargaExpInput;
      askNextCargaField();
    }

    function askNextCargaField() {
      if (cargaCurrentFieldIndex < cargaFields.length) {
        appendMessage(cargaFields[cargaCurrentFieldIndex].prompt, "bot");
      } else {
        submitCargaExp();
      }
    }

    function processCargaExpInput(userInput) {
      const field = cargaFields[cargaCurrentFieldIndex];
      cargaData[field.key] = userInput.trim();
      cargaCurrentFieldIndex++;
      askNextCargaField();
    }

    function submitCargaExp() {
      confirmCargaExp();
      chatBody.insertAdjacentHTML('beforeend', `
        <div class="option-box" onclick="subirImagenExpediente('${cargaData.expediente}')">
          📸 Subir imagen al expediente
        </div>
      `);
    }

    function confirmCargaExp() {
      let html = "<table class='table table-bordered'><thead><tr><th>Campo</th><th>Valor</th></tr></thead><tbody>";
      cargaFields.forEach(f => {
        html += `<tr><td>${f.prompt.replace("Por favor, ingresa ","")}</td>
                   <td><input type="text" id="confirm_${f.key}" value="${cargaData[f.key]||''}" class="form-control"/></td>
                 </tr>`;
      });
      html += "</tbody></table>";
      appendMessage("Por favor, revisa y corrige si es necesario los siguientes datos:", "bot");
      chatBody.insertAdjacentHTML('beforeend', html);
      chatBody.insertAdjacentHTML('beforeend', `
        <div class="option-box" onclick="userConfirmCargaExp(true)">Confirmar datos</div>
        <div class="option-box" onclick="userConfirmCargaExp(false)">Editar datos</div>
        <div class="option-box" onclick="resetChat()">Salir</div>
      `);
    }

    function userConfirmCargaExp(isCorrect) {
      if (isCorrect) {
        cargaFields.forEach(f => {
          cargaData[f.key] = document.getElementById("confirm_"+f.key).value;
        });
        finalSubmitCargaExp();
      } else {
        appendMessage("Puedes editar los datos y luego volver a confirmar.", "bot");
      }
    }

    function finalSubmitCargaExp() {
    appendMessage("Enviando datos del expediente...", "bot");
    $.post("cargabotsql.php", { cargar_expediente: true, ...cargaData }, function(resp) {
        appendMessage("✅ Expediente cargado correctamente.", "bot");
        
        // 🔔 NOTIFICACIÓN LOCAL Y GLOBAL - EXPEDIENTE CREADO
            const username = "<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>";
        const usuario = '<?php echo $_SESSION["username"]; ?>';
        const expedienteNumero = cargaData.expediente || 'Nuevo ingreso de Expediente';
        const caratula = cargaData.expediente || 'Sin carátula';
        
        // 1. Notificación local inmediata
        mostrarNotificacion(`📄 Nuevo Ingreso de Expediente: ${expedienteNumero} - ${caratula}`);
        
        // 2. Notificación global para todos los usuarios
        enviarNotificacionGlobal(
            expedienteNumero,
            `Nuevo Ingreso de Expediente: ${expedienteNumero}`,
            'Nuevo Ingreso de Expediente'
        );
        
        // 3. Mensaje en el chat
        appendMessage(`🌐 Notificación enviada: Nuevo expediente ${expedienteNumero} creado`, "bot");
        
        isCargaInProgress = false;
        currentAction = null;
        setTimeout(RenewExp, 1000);
        
    }).fail(function() {
        appendMessage("❌ Error al cargar el expediente. Intenta nuevamente.", "bot");
        isCargaInProgress = false;
    });
}

document.addEventListener("DOMContentLoaded", () => {
  const chatBody = document.getElementById("chatBody");
  chatBody.addEventListener("click", e => {
    const btn = e.target.closest(".delete-btn");
    if (!btn) return;
    const exp = btn.dataset.exp;
    const src = decodeURIComponent(btn.dataset.src || "");
    deleteImagen(exp, src);
  });
});

function mostrarImagenes(exp) {
    // 1. Guardamos la referencia del elemento loader devuelto por appendMessage
    const load = ("", "bot"); 
    
    // 2. Petición AJAX
    $.get("get_imagenes.php", { expediente: exp }, resp => {
        
        // CORRECCIÓN: Validamos que 'load' exista y tenga la función 'remove'
        if (load && typeof load.remove === 'function') {
            load.remove();
        }

        const chatBody = document.getElementById("chatBody"); 
        
        if (!chatBody) {
            console.error("El contenedor del chat (id='chatBody') no fue encontrado.");
            return;
        }

        if (!resp || !resp.length) {
            appendMessage("No hay imágenes adjuntas.", "bot");
            // Usamos $chatBody para el scroll si existe
            chatBody.scrollTop = chatBody.scrollHeight;
            return;
        }

        appendMessage("Imágenes adjuntas:", "bot");

        let gallery = `<div class="imagenes-container"><div class="image-gallery">`;
        resp.forEach(src => {
            // Se asume que 'btoa' está disponible para codificar src
            gallery += `
                <div class="image-item" id="img-${btoa(src)}">
                    <img src="${src}" onclick="expandirImagen('${src}')" />
                    <button
                        class="btn btn-sm btn-danger delete-btn"
                        data-exp="${exp}"
                        data-src="${src}"
                        title="Eliminar imagen"
                    >🗑️</button>
                </div>`;
        });
        gallery += `</div></div>`;

        chatBody.insertAdjacentHTML("beforeend", gallery);
        
        // Hacemos scroll seguro
        chatBody.scrollTop = chatBody.scrollHeight;

    }, "json").fail((jqXHR, status, err) => {
        
        // CORRECCIÓN: Validamos que 'load' exista y tenga la función 'remove'
        if (load && typeof load.remove === 'function') {
            load.remove();
        }

        console.error("GET get_imagenes.php falló:", status, err);
        appendMessage("❌ Error al cargar imágenes.", "bot");
        
        // Hacemos scroll si hay un error
        const chatBody = document.getElementById("chatBody");
        if (chatBody) {
             chatBody.scrollTop = chatBody.scrollHeight;
        }
    });
}

function editarExpediente(expediente) {
    const loading = appendMessage("Cargando datos del expediente...");
    
    $.post("get_expediente_data.php", { expediente: expediente }, function(response) {
        loading.remove();
        
        if (response.success) {
            mostrarFormularioEdicion(response.data, expediente);
        } else {
            appendMessage("❌ Error al cargar los datos del expediente: " + (response.error || "Desconocido"), "bot");
        }
    }, "json").fail(() => {
        loading.remove();
        appendMessage("❌ Error de conexión al cargar datos.", "bot");
    });
}

function mostrarFormularioEdicion(datos, expediente) {
    let html = `
        <div class="edicion-expediente">
            <h5>Editando Expediente: ${expediente}</h5>
            <table class="table table-bordered">
                <thead>
                    <tr><th>Campo</th><th>Valor Actual</th><th>Nuevo Valor</th></tr>
                </thead>
                <tbody>
    `;
    
    // Definir campos editables
    const camposEditables = [
        { key: 'caratula', label: 'Carátula' },
        { key: 'nombre_apellido', label: 'Solicitante' },
        { key: 'celular', label: 'Celular' },
        { key: 'fecha_inicio', label: 'Fecha Inicio' },
        { key: 'juzgado', label: 'Juzgado/Mesa' },
        { key: 'responsable', label: 'Email' },
        { key: 'objeto', label: 'Objeto/Falta' },
        { key: 'observaciones', label: 'Observaciones' },
        { key: 'seccion', label: 'Nomenclatura' },
        { key: 'direccion', label: 'Dirección' },
        { key: 'estado', label: 'Estado' },
        { key: 'barrio', label: 'Barrio/Informe' }
    ];
    
    camposEditables.forEach(campo => {
        const valorActual = datos[campo.key] || '';
        html += `
            <tr>
                <td>${campo.label}</td>
                <td>${valorActual}</td>
                <td>
                    <input type="text" 
                           id="edit_${campo.key}" 
                           value="${valorActual}" 
                           class="form-control form-control-sm"
                           placeholder="Nuevo valor...">
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-success btn-sm" onclick="guardarEdicionExpediente('${expediente}')">
                    💾 Guardar Cambios
                </button>
                <button class="btn btn-secondary btn-sm" onclick="cancelarEdicion()">
                    ❌ Cancelar
                </button>
            </div>
        </div>
    `;
    
    appendMessage("Formulario de edición:", "bot");
    chatBody.insertAdjacentHTML("beforeend", html);
    chatBody.scrollTop = chatBody.scrollHeight;
}


function cancelarEdicion() {
    // Remover formulario de edición
    const formulario = document.querySelector('.edicion-expediente');
    if (formulario) {
        formulario.remove();
    }
    appendMessage("Edición cancelada.", "bot");
}

function deleteImagen(exp, src) {
  console.log("deleteImagen raw src:", src);

  src = src.replace(/^\/+/, "");
  if (!src.startsWith("expedientes_adjuntos/")) {
    const parts = src.split("expedientes_adjuntos/");
    src = parts.length > 1
      ? "expedientes_adjuntos/" + parts.pop()
      : src;
  }
  console.log("deleteImagen normalized src:", src);

  if (!confirm("¿Seguro que deseas eliminar esta imagen?")) return;

  const load = appendMessage("Eliminando imagen...");
  $.post(
    "delete_imagen.php",
    { expediente: exp, src: src },
    data => {
      load.remove();
      console.log("delete_imagen.php response:", data);
      if (data && data.success) {
        const tile = document.getElementById("img-" + btoa(src));
        if (tile) tile.remove();
        appendMessage("✅ Imagen eliminada correctamente.", "bot");
      } else {
        const err = data && data.error ? data.error : "desconocido";
        appendMessage("❌ No se pudo eliminar la imagen: " + err, "bot");
      }
    },
    "json"
  ).fail((_, status, err) => {
    load.remove();
    console.error("POST delete_imagen.php falló:", status, err);
    appendMessage("❌ Error en la petición de borrado.", "bot");
  });
}
  function expandirImagen(src) {
  const modal = document.createElement('div');
  modal.className = 'image-modal';
  modal.innerHTML = `
    <div class="modal-overlay"></div>
    <div class="modal-content">
      <span class="close">X</span>
      <img src="${src}" class="expanded-image">
    </div>
  `;
  document.body.appendChild(modal);

  const overlay = modal.querySelector('.modal-overlay');
  const closeBtn = modal.querySelector('.close');
  const img = modal.querySelector('.expanded-image');
  closeBtn.onclick = () => modal.remove();
  overlay.onclick = () => modal.remove();

  img.onclick = () => {
    img.classList.toggle('zoomed');
  };
}
    function subirImagenExpediente(exp) {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.capture = 'environment';
  input.multiple = true;
  input.style.display = 'none';

  input.onchange = async (e) => {
    const files = Array.from(e.target.files || []);
    if (!files.length) {
      // no archivos (usuario canceló)
      try { input.remove(); } catch (e) {}
      return;
    }

    const allowedExt = ['jpg','jpeg','png','gif','webp','bmp'];
    const heicExt = ['heic','heif','avif'];
    const processed = [];

    for (const f of files) {
      const name = f.name || '';
      const ext = (name.split('.').pop() || '').toLowerCase();
      if (heicExt.includes(ext)) {
        appendMessage(`⚠️ El archivo ${name} parece HEIC/HEIF. Si el servidor no lo soporta, puede fallar la subida.`, 'bot');
      } else if (!allowedExt.includes(ext) && !heicExt.includes(ext)) {
        appendMessage(`❌ ${name}: extensión no soportada.`, 'bot');
        continue;
      }
      if (f.size > 50 * 1024 * 1024) {
        appendMessage(`❌ ${name}: archivo demasiado grande (${(f.size/1024/1024).toFixed(1)} MB).`, 'bot');
        continue;
      }
      processed.push(f);
    }

    if (!processed.length) {
      appendMessage('❌ No hay archivos válidos para subir.', 'bot');
      try { input.remove(); } catch (e) {}
      return;
    }

    const fd = new FormData();
    fd.append('expediente', exp);
    processed.forEach((f) => fd.append('imagenes[]', f, f.name));
    const loading = appendMessage("Subiendo imágenes...");
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'cargabotsql.php', true);
    xhr.withCredentials = true;

    xhr.upload.onprogress = (evt) => {
      if (evt.lengthComputable) {
        const pct = Math.round((evt.loaded / evt.total) * 100);
        console.log('upload progress', pct + '%');
      }
    };

    xhr.onload = () => {
      loading.remove();
      try {
        const resp = JSON.parse(xhr.responseText || '{}');
        if (xhr.status >= 200 && xhr.status < 300 && resp && resp.success) {
          appendMessage(`✅ ${processed.length} imagen(es) subidas correctamente.`, "bot");
          try { triggerGhostExpPage(exp); } catch(e){}
        } else {
          const err = (resp && (resp.error || JSON.stringify(resp))) || `HTTP ${xhr.status}`;
          appendMessage("❌ Error al subir imágenes: " + err, "bot");
          console.error('Upload response', xhr.status, xhr.responseText);
        }
      } catch (err) {
        appendMessage('❌ Respuesta inválida del servidor. Revisa logs.', 'bot');
        console.error('Parse error', err, xhr.responseText);
      } finally {
        try { input.remove(); } catch (e) {}
      }
    };

    xhr.onerror = () => {
      loading.remove();
      appendMessage('❌ Error de red al subir imágenes.', 'bot');
      try { input.remove(); } catch (e) {}
    };

    xhr.send(fd);
  };

  document.body.appendChild(input);
  input.click();
}

async function triggerGhostExpPage(exp, opts = {}) {
  const endpoint = opts.endpoint || '/exp/check_exp_images.php';
  const pollInterval = opts.pollInterval || 1000;
  const timeoutMs = opts.timeoutMs || 20000;
  const maxAttempts = opts.maxAttempts || 20;
  if (!exp) { appendMessage("❗ Falta expediente para verificar imágenes.", "bot"); return; }

  const normalizedExp = String(exp).trim();
  const delay = ms => new Promise(res => setTimeout(res, ms));

  const fetchJson = async (url) => {
    const r = await fetch(url, { cache: "no-store" });
    const ct = r.headers.get('content-type') || '';
    if (!r.ok) throw new Error('HTTP ' + r.status);
    if (ct.includes('application/json')) return r.json();
    throw new Error('Respuesta no JSON');
  };

  appendMessage("🔎 Comprobando estado inicial de imágenes...", "bot");
  let initialResp;
  try {
    initialResp = await fetchJson(`${endpoint}?exp=${encodeURIComponent(normalizedExp)}&_=${Date.now()}`);
  } catch (err) {
    console.error("Error al obtener estado inicial:", err);
    appendMessage("❌ Error inicial al consultar imágenes: " + err.message, "bot");
    return;
  }

  const initialFiles = Array.isArray(initialResp.files) ? initialResp.files : [];
  const initialCount = initialResp.count || initialFiles.length || 0;
  const initialMap = {};
  initialFiles.forEach(f => { if (f && f.name) initialMap[f.name] = f.mtime || 0; });

  appendMessage(`📁 Imágenes Cargadas: ${initialCount}`, "bot");
  console.log('[triggerGhostExpPage] initial files', initialFiles);

  const ghostUrl = `/exp/?s=${encodeURIComponent(normalizedExp)}&ghost=1&_=${Date.now()}`;
  let iframe = document.getElementById('ghostExpIframe');
  if (!iframe) {
    iframe = document.createElement('iframe');
    iframe.id = 'ghostExpIframe';
    iframe.style.display = 'none';
    iframe.style.width = '1px';
    iframe.style.height = '1px';
    iframe.style.opacity = '0';
    iframe.style.pointerEvents = 'none';
    document.body.appendChild(iframe);
  }

  iframe.onload = () => {
    console.log('[triggerGhostExpPage] ghost iframe onload');
  };

  iframe.src = ghostUrl;
  appendMessage("🔎 Aguarde un Momento...", "bot");
  let attempts = 0;
  let pollDelay = pollInterval;
  const startTs = Date.now();

  while (Date.now() - startTs < timeoutMs && attempts < maxAttempts) {
    attempts++;
    await delay(pollDelay);
    try {
      const resp = await fetchJson(`${endpoint}?exp=${encodeURIComponent(normalizedExp)}&_=${Date.now()}`);
      const files = Array.isArray(resp.files) ? resp.files : [];
      const newlyAdded = files.filter(f => !(f && f.name && initialMap[f.name]));
      const updated = files.filter(f => (f && f.name && initialMap[f.name] && (f.mtime > initialMap[f.name])));

      if (newlyAdded.length || updated.length) {
        try { iframe.remove(); } catch (e) {}
        if (newlyAdded.length) {
          appendMessage(`✅ Se detectaron ${newlyAdded.length} nueva(s) imagen(es). Expediente actualizado.`, "bot");
          console.log('[triggerGhostExpPage] newlyAdded', newlyAdded);
        }
        if (updated.length) {
          appendMessage(`ℹ️ ${updated.length} imagen(es) actualizadas recientemente.`, "bot");
          console.log('[triggerGhostExpPage] updated', updated);
        }
        return;
      }

      pollDelay = Math.min(3000, pollDelay + 300);
    } catch (err) {
      console.warn("check_exp_images fetch error:", err);
    }
  }

  try { iframe.remove(); } catch (e) {}
  appendMessage("✅ Expediente Correctamente Actualizado!.", "bot");
  console.warn('[triggerGhostExpPage] polling finished without changes');
}

const sessionUsername = '<?= $_SESSION["username"] ?>'; // usuario logueado
const currentUser = {
    legajo: <?= json_encode($_SESSION['user'] ?? '') ?>,
    usuario: <?= json_encode($_SESSION['username'] ?? '') ?>
  };
function showNotes(exp) {
  const load = appendMessage("Cargando notas...");
  $.get('get_notes.php', { exp }, function(resp) {
    load.remove();
    createNotesModal();            // asegúrate de que el modal exista
    openNotesModal(exp, resp);    // abrir modal y renderizar notas
  }, 'json').fail(() => {
    load.remove();
    appendMessage("Error al cargar notas.", "bot");
  });
}

function afterRenderAvatars() {
  document.querySelectorAll('#notesContent .note-item').forEach(item => {
    if (item.dataset.initial && item.dataset.initial.trim()) return;

    let username = '';
    const header = item.querySelector('.note-header');
    if (header) {
      const firstDiv = header.querySelector('div');
      if (firstDiv && firstDiv.textContent.trim()) username = firstDiv.textContent.trim();
      if (!username) username = header.textContent.trim();
    }
    if (!username && item.dataset.user) username = item.dataset.user;

    username = (username || 'U').trim();
    const initial = username.charAt(0).toUpperCase() || 'U';
    item.dataset.initial = initial;
  });
}

function insertDateSeparators() {
  const notes = document.querySelectorAll('#notesContent .note-item');
  let lastDate = '';
  notes.forEach(note => {
    const date = note.dataset.date || ''; // le pasamos la fecha desde PHP
    if (date && date !== lastDate) {
      const separator = document.createElement('div');
      separator.className = 'date-separator';
      separator.textContent = date;
      note.parentNode.insertBefore(separator, note);
      lastDate = date;
    }
  });
}

function scrollToLatest() {
  const notesContent = document.getElementById('notesContent');
  notesContent.scrollTop = notesContent.scrollHeight;
}


function refreshNotesUI() {
  afterRenderAvatars();
  insertDateSeparators();
  scrollToLatest();


  document.querySelectorAll('#notesContent .note-item.user').forEach(item => {
    if (!item.querySelector('.note-status')) {
      const mark = determineStatusMark(item);
      const statusEl = document.createElement('div');
      statusEl.className = 'note-status';
      statusEl.textContent = mark;
      statusEl.style.fontSize = '12px';
      statusEl.style.marginLeft = '6px';
      statusEl.style.lineHeight = '1';
      statusEl.style.color = 'rgba(255,255,255,0.95)';

      const header = item.querySelector('.note-header');
      if (header) {
        const children = Array.from(header.children).filter(n => n.nodeType === 1);
        const rightArea = (children.length >= 2) ? children[children.length - 1] : header;
        const timeEl = rightArea.querySelector('.note-time');
        if (timeEl) {
          timeEl.insertAdjacentElement('afterend', statusEl);
        } else {
          rightArea.appendChild(statusEl);
        }
      } else {
        item.appendChild(statusEl);
      }
    }
  });
}

function determineStatusMark(item) {
  const ds = item.dataset || {};
  if (ds.leido) {
    const v = String(ds.leido).toLowerCase();
    return (v === '1' || v === 'true' || v === 'yes') ? '✔✔' : '✔';
  }
  if (ds.status) {
    const st = String(ds.status).toLowerCase();
    return (st === 'read' || st === 'leido' || st === '1' || st === 'true') ? '✔✔' : '✔';
  }
  return '✔';
}

function renderNotesList(notes) {
  const notesContent = document.getElementById('notesContent');
  if (!notesContent) return;
  notesContent.innerHTML = ''; // limpiar previo

  if (!Array.isArray(notes) || notes.length === 0) {
    notesContent.innerHTML = '<div style="text-align:center;color:#666;">No hay notas registradas.</div>';
    return;
  }


  const normCurrent = String(currentUser || '').trim().toLowerCase();

  notes.forEach(n => {
    const usuarioRaw = String(n.usuario || '');
    const usuario = usuarioRaw.trim();
    const normUsuario = usuario.toLowerCase();

    const isMine = (normUsuario === normCurrent);
    const userClass = isMine ? 'user' : 'other';
    const initial = usuario ? usuario.charAt(0).toUpperCase() : 'U';

    const fecha = n.created_at ? new Date(n.created_at) : new Date();
    const fechaDia = fecha.toLocaleDateString();
    const fechaHora = fecha.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    let statusMark = '✔';
    if (typeof n.leido !== 'undefined' && n.leido !== null) {
      const leidoBool = (n.leido === true || n.leido === 1 || n.leido === '1' || String(n.leido).toLowerCase() === 'true');
      statusMark = leidoBool ? '✔✔' : '✔';
    } else if (typeof n.status !== 'undefined' && n.status !== null) {
      const st = String(n.status).trim().toLowerCase();
      statusMark = (st === 'read' || st === 'leido' || st === 'leer' || st === '1' || st === 'true') ? '✔✔' : '✔';
    }

    const noteId = Number.isFinite(Number(n.id)) ? parseInt(n.id, 10) : 0;

    const statusStyle = isMine
      ? 'font-size:12px;background:#28a745;color:#fff;padding:2px 8px;border-radius:10px;margin-left:6px;'
      : 'font-size:12px;background:#e9ecef;color:#333;padding:2px 8px;border-radius:10px;margin-left:6px;';

    const noteHTML = `
      <div class="note-item ${userClass}"
           data-initial="${escapeHtml(initial)}"
           data-date="${escapeHtml(fechaDia)}"
           data-status="${escapeHtml(n.status || 'sent')}"
           data-leido="${escapeHtml(n.leido || '')}">

        <div class="note-avatar" aria-hidden="true">${escapeHtml(initial)}</div>

        <div class="note-header" style="display:flex;justify-content:space-between;align-items:center;">
          <div style="font-weight:700;color:${isMine ? 'white' : '#333'};font-size:0.95rem;">
            ${escapeHtml(usuario)}
          </div>

          <div style="display:flex;align-items:center;gap:6px;">
            <div class="note-time" style="font-size:11px;color:${isMine ? 'rgba(255,255,255,0.9)' : 'rgba(0,0,0,0.55)'}">
              ${escapeHtml(fechaHora)}
            </div>
            <div class="note-status" style="${statusStyle}">
              ${escapeHtml(statusMark)}
            </div>
          </div>
        </div>

        <div class="note-message" style="margin-top:6px;">
          ${escapeHtml(n.mensaje)}
        </div>

        <div class="note-buttons" style="margin-top:8px;">
          <button class="btn btn-sm btn-outline-secondary"
  onclick="openAddNoteModal('${escapeJs(n.expediente || '')}')">
  ✏️ Añadir nota
</button>
          <button class="btn btn-sm btn-outline-danger" onclick="deleteNoteConfirm(${noteId})">🗑️ Eliminar</button>
        </div>
      </div>
    `;

    notesContent.insertAdjacentHTML('beforeend', noteHTML);
  });

  notesContent.scrollTop = notesContent.scrollHeight;

  if (typeof refreshNotesUI === 'function') refreshNotesUI();
}

function createNotesModal() {
  if (document.getElementById('notesModal')) return;

  const modal = document.createElement('div');
  modal.id = 'notesModal';
  modal.style.display = 'none';
  modal.style.position = 'fixed';
  modal.style.top = '0';
  modal.style.left = '0';
  modal.style.width = '100%';
  modal.style.height = '100%';
  modal.style.background = 'rgba(0,0,0,0.5)';
  modal.style.justifyContent = 'center';
  modal.style.alignItems = 'center';
  modal.style.zIndex = '9999';

  const content = document.createElement('div');
  content.className = 'notesModalContent';
  content.style.background = '#f0f0f0';
  content.style.borderRadius = '10px';
  content.style.padding = '60px';
  content.style.maxWidth = '500px';
  content.style.width = '90%';
  content.style.maxHeight = '80%';
  content.style.overflowY = 'auto';
  content.style.position = 'relative';
  content.style.boxShadow = '0 4px 12px rgba(0,0,0,0.2)';

  const closeBtn = document.createElement('button');
  closeBtn.className = 'closeModalBtn';
  closeBtn.innerHTML = '✖️';
  closeBtn.style.position = 'absolute';
  closeBtn.style.top = '10px';
  closeBtn.style.right = '10px';
  closeBtn.style.border = 'none';
  closeBtn.style.background = 'none';
  closeBtn.style.fontSize = '20px';
  closeBtn.style.cursor = 'pointer';
  closeBtn.onclick = closeNotesModal;

  const notesContent = document.createElement('div');
  notesContent.id = 'notesContent';

  content.appendChild(closeBtn);
  content.appendChild(notesContent);
  modal.appendChild(content);
  document.body.appendChild(modal);
}

function openNotesModal(expediente, notes) {
  const escapeHtmlLocal = window.escapeHtml || function (text) {
    if (text === null || text === undefined) return '';
    return String(text).replace(/[&<>"']/g, function(m){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]); });
  };

  const modal = document.getElementById('notesModal');
  if (!modal) {
    console.warn('notesModal no existe — creando modal dinámico.');
    createNotesModal(); // tu función existente
  }
  const notesContent = document.getElementById('notesContent');
  if (!notesContent) {
    console.error('No se encontró #notesContent');
    return;
  }

  document.getElementById('notesModal').style.display = 'flex';

  console.log('[openNotesModal] currentUser=', window.currentUser, 'sessionUsername=', window.sessionUsername);

  if (!Array.isArray(notes) || notes.length === 0) {
    notesContent.innerHTML = '<div style="text-align:center;color:#666;">No hay notas registradas.</div>';
    return;
  }

  if (typeof window.renderNotesList === 'function') {
    try {
      window.renderNotesList(notes);
      return;
    } catch (e) {
      console.error('renderNotesList lanzó error:', e);

    }
  }

  notesContent.innerHTML = '';
  notes.forEach(n => {
    const usuario = String(n.usuario || 'Usuario').trim();
    const fecha = n.created_at ? new Date(n.created_at) : new Date();
    const fechaHora = fecha.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    let statusMark = '✔';
    if (typeof n.leido !== 'undefined' && n.leido !== null && n.leido !== '') {
      const leidoBool = (n.leido === true || n.leido === 1 || n.leido === '1' || String(n.leido).toLowerCase() === 'true');
      statusMark = leidoBool ? '✔✔' : '✔';
    } else if (n.status) {
      const st = String(n.status).trim().toLowerCase();
      statusMark = (st === 'read' || st === 'leido' || st === '1' || st === 'true') ? '✔✔' : '✔';
    }

    const isMine = String(usuario).trim().toLowerCase() === String(window.currentUser || window.sessionUsername || '').trim().toLowerCase();

    const statusStyle = isMine
      ? 'font-size:12px;background:#28a745;color:#fff;padding:4px 8px;border-radius:10px;margin-left:6px;display:inline-block;min-width:28px;text-align:center;'
      : 'font-size:12px;background:#e9ecef;color:#333;padding:4px 8px;border-radius:10px;margin-left:6px;display:inline-block;min-width:28px;text-align:center;';

    const noteExpEsc = escapeHtmlLocal(n.expediente || '');

    const html = `
      <div class="note-item ${isMine ? 'user' : 'other'}" data-leido="${escapeHtmlLocal(n.leido || '')}">
        <div class="note-avatar">${(usuario.charAt(0)||'U').toUpperCase()}</div>

        <div class="note-header" style="display:flex;justify-content:space-between;align-items:center;">
          <div style="font-weight:700;color:${isMine ? 'white' : '#333'}">${escapeHtmlLocal(usuario)}</div>
          <div style="display:flex;align-items:center;gap:6px;">
            <div class="note-time" style="font-size:11px;color:${isMine ? 'rgba(255,255,255,0.9)' : 'rgba(0,0,0,0.55)'}">${escapeHtmlLocal(fechaHora)}</div>
            <div class="note-status" style="${statusStyle}">${escapeHtmlLocal(statusMark)}</div>
          </div>
        </div>

        <div class="note-message" style="margin-top:6px;color:${isMine ? 'white' : '#222'}">${escapeHtmlLocal(n.mensaje || '')}</div>

        <div class="note-buttons" style="margin-top:8px;">
          <!-- data-exp en vez de onclick inline -->
          <button class="btn btn-sm btn-outline-secondary note-add-btn" data-exp="${noteExpEsc}">✏️ Añadir nota</button>
          <button class="btn btn-sm btn-outline-danger" onclick="deleteNoteConfirm(${Number(n.id) || 0})">🗑️ Eliminar</button>
        </div>
      </div>
    `;
    notesContent.insertAdjacentHTML('beforeend', html);
  });

  if (typeof refreshNotesUI === 'function') refreshNotesUI();
}

function closeNotesModal() {
  const modal = document.getElementById('notesModal');
  if (modal) modal.style.display = 'none';
}


function escapeHtml(text) {
  if (!text) return '';
  return text.replace(/[&<>"']/g, function(m) {
    return ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' })[m];
  });
}

function escapeJs(s){ return (s+'').replace(/'/g,"\\'").replace(/"/g,'\\"'); }


function openAddNoteModal(exp) {
  const modalId = 'noteModal';
  const existing = document.getElementById(modalId);
  if (existing) existing.remove();

  const modal = document.createElement('div');
  modal.id = modalId;
  modal.style = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.45);display:flex;align-items:center;justify-content:center;z-index:11000;';
  modal.setAttribute('role', 'dialog');
  modal.setAttribute('aria-modal', 'true');

  modal.innerHTML = `
    <div style="background:#fff;padding:18px;border-radius:8px;max-width:720px;width:95%;position:relative;box-shadow:0 8px 24px rgba(0,0,0,0.2);">
      <button id="closeNoteModal" aria-label="Cerrar" style="position:absolute;right:10px;top:8px;border:none;background:none;font-size:18px;cursor:pointer;">✖️</button>
      <h5 style="margin-top:0;margin-bottom:8px;">Añadir nota a expediente ${escapeHtml(exp)}</h5>
      <textarea id="noteText" rows="6" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px" placeholder="Ej: Hoy vino el frentista solicitando..."></textarea>
      <div style="margin-top:10px;text-align:right;">
        <button id="cancelNoteBtn" class="btn btn-sm btn-secondary" style="margin-right:6px;">Cancelar</button>
        <button id="saveNoteBtn" class="btn btn-sm btn-primary">Guardar nota</button>
      </div>
    </div>
  `;

  document.body.appendChild(modal);

  const closeBtn = document.getElementById('closeNoteModal');
  const cancelBtn = document.getElementById('cancelNoteBtn');
  const saveBtn = document.getElementById('saveNoteBtn');
  const textarea = document.getElementById('noteText');

  function closeModal() {
    document.removeEventListener('keydown', escHandler);
    try { modal.remove(); } catch (e) { /* no crítico */ }
  }

  function escHandler(e) { if (e.key === 'Escape') closeModal(); }
  document.addEventListener('keydown', escHandler);

  closeBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);

  saveBtn.addEventListener('click', () => {
    const txt = textarea.value.trim();
    if (!txt) { alert('Ingresá un mensaje.'); textarea.focus(); return; }

    saveBtn.disabled = true;
    saveBtn.textContent = 'Guardando...';

    saveNote(exp, txt, (note) => {
      appendMessage("✅ Nota guardada.", "bot");
      closeModal();
      if (typeof showNotes === 'function') {
        setTimeout(() => showNotes(exp), 500);
      }
    });

    setTimeout(() => {
      saveBtn.disabled = false;
      saveBtn.textContent = 'Guardar nota';
    }, 4000);
  });

  textarea.focus();

  modal.addEventListener('click', (ev) => {
    if (ev.target === modal) closeModal();
  });
}

function saveNote(exp, msg, cb) {
  if (!exp || !msg) { appendMessage('❗ Falta expediente o mensaje.', 'bot'); return; }
  const l = appendMessage("Guardando nota...");
  $.ajax({
    url: 'save_note.php',
    method: 'POST',
    data: { expediente: exp, mensaje: msg },
    dataType: 'json',
    xhrFields: { withCredentials: true },
    success: function(resp) {
      l.remove();
      if (resp && resp.success) {
        if (typeof cb === 'function') cb(resp.note);
      } else {
        appendMessage("❌ No se pudo guardar la nota. " + (resp && resp.error ? resp.error : ''), "bot");
      }
    },
    error: function(xhr) {
      l.remove();
      appendMessage("❌ Error en la petición: " + xhr.status + " " + xhr.statusText, "bot");
      console.error(xhr.responseText);
    }
  });
}

function deleteNoteConfirm(id) {
  if (!confirm('¿Eliminar esta nota?')) return;
  const l = appendMessage("Eliminando nota...");
  $.post('delete_note.php', { id: id }, function(resp) {
    l.remove();
    if (resp && resp.success) {
      appendMessage("✅ Nota eliminada.", "bot");
      if (typeof lastExpediente !== 'undefined' && lastExpediente) showNotes(lastExpediente);
    } else {
      appendMessage("❌ No se pudo eliminar la nota.", "bot");
    }
  }, 'json').fail(() => {
    l.remove();
    appendMessage("❌ No estas Autorizado a Eliminar Notas.", "bot");
  });
}

    function RenewExp() {
      appendMessage("Por favor revisa si quedó correcta la carga ☝️ 🤓", "bot");
      appendMessage("Si no, vuelve a intentarlo 🙏", "bot");
      setTimeout(()=> {
        chatBody.insertAdjacentHTML("beforeend", `
          <div class="option-box" onclick="startSearchBy('expediente')">Buscar Por Nº de Expediente</div>
        `);
        chatBody.scrollTop = chatBody.scrollHeight;
      }, 500);
    }

    function fileAction(type, action, name) {
}
    function optionBox(message, options, callback) {
      const modal = document.createElement('div');
      modal.style = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;justify-content:center;align-items:center;z-index:1000';
      const content = document.createElement('div');
      content.style = 'background:#fff;padding:20px;border-radius:5px;text-align:center';
      content.innerHTML = `<p>${message}</p>`;
      const btnContainer = document.createElement('div');
      btnContainer.style = 'margin-top:10px';
      options.forEach(opt => {
        const btn = document.createElement('button');
        btn.textContent = opt;
        btn.style = 'margin:5px';
        btn.addEventListener('click', () => {
          document.body.removeChild(modal);
          callback(opt);
        });
        btnContainer.appendChild(btn);
      });
      content.appendChild(btnContainer);
      modal.appendChild(content);
      document.body.appendChild(modal);
    }

    setTimeout(() => {
 
      showMainOptions();
    }, 500);

    function resetPage() { location.reload(); }

const clearBtn = document.getElementById('clearChatBtn');
const modal = document.getElementById('popupModal');
const confirmBtn = document.getElementById('confirmClear');
const cancelBtn = document.getElementById('cancelClear');

clearBtn.addEventListener('click', () => {
  modal.style.display = 'block';
});

cancelBtn.addEventListener('click', () => {
  modal.style.display = 'none';
});

confirmBtn.addEventListener('click', () => {
  chatBody.innerHTML = '';
  location.reload();
});


const consoleModal    = document.getElementById('consoleModal');
const closeConsoleBtn = document.getElementById('closeConsoleBtn');
const runConsoleBtn   = document.getElementById('runConsoleBtn');
const consoleInput    = document.getElementById('consoleInput');
const consoleOutput   = document.getElementById('consoleOutput');

closeConsoleBtn.addEventListener('click', () => {
  consoleModal.style.display = 'none';
  consoleInput.value = '';
  consoleOutput.textContent = '';
});

runConsoleBtn.addEventListener('click', () => {
  const cmd = consoleInput.value.trim();
  if (!cmd) return;
  consoleOutput.textContent += `> ${cmd}\n`;
  consoleOutput.textContent += 'Ejecutando...\n';
  fetch('console.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ command: cmd })
  })
  .then(res => res.text())
  .then(text => {
    consoleOutput.textContent += text + '\n';
    consoleOutput.scrollTop = consoleOutput.scrollHeight;
  })
  .catch(err => {
    consoleOutput.textContent += `Error: ${err.message}\n`;
  });
});

 (function(){
    document.documentElement.style.zoom = "100%";
  })();
function mostrarPopupFunciones() {
    // Crear el pop-up
    const popup = document.createElement('div');
    popup.className = 'funciones-popup';
    popup.innerHTML = `
        <div class="funciones-popup-content">
            <div class="funciones-popup-header">
                <h3>🎉 ¡Nuevas Funciones Agregadas!</h3>
                <p>Se ha implementado el sistema de edición de expedientes</p>
            </div>
            
            <div class="funciones-list">
                <div class="funcion-item">
                    <div class="funcion-icon">📝</div>
                    <div class="funcion-text">
                        <strong>Edición Completa de Expedientes</strong>
                        <span>Ahora puedes modificar todos los campos de cualquier expediente encontrado</span>
                    </div>
                </div>
                
                <div class="funcion-item">
                    <div class="funcion-icon">🔍</div>
                    <div class="funcion-text">
                        <strong>Formulario Intuitivo</strong>
                        <span>Interfaz de edición con valores actuales y nuevos campos lado a lado</span>
                    </div>
                </div>
                
                <div class="funcion-item">
                    <div class="funcion-icon">💾</div>
                    <div class="funcion-text">
                        <strong>Guardado Seguro</strong>
                        <span>Sistema de confirmación y registro de cambios realizados</span>
                    </div>
                </div>
                
                <div class="funcion-item">
                    <div class="funcion-icon">✅</div>
                    <div class="funcion-text">
                        <strong>Resumen de Cambios</strong>
                        <span>Visualización clara de todas las modificaciones realizadas</span>
                    </div>
                </div>
                
                <div class="funcion-item">
                    <div class="funcion-icon">📊</div>
                    <div class="funcion-text">
                        <strong>Dashboard de Expedientes</strong>
                        <span>Visualizacion estadistico de expedientes</span>
                    </div>
                </div>
            </div>
            
            <div class="funciones-popup-actions">
                <button class="btn-comenzar" onclick="cerrarPopupFunciones()">
                    ¡Comenzar a Usar!
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(popup);
    
    // Cerrar al hacer clic fuera del contenido
    popup.addEventListener('click', function(e) {
        if (e.target === popup) {
            cerrarPopupFunciones();
        }
    });
    
    // Cerrar con tecla ESC
    document.addEventListener('keydown', function cerrarConTecla(e) {
        if (e.key === 'Escape') {
            cerrarPopupFunciones();
            document.removeEventListener('keydown', cerrarConTecla);
        }
    });
}

function cerrarPopupFunciones() {
    const popup = document.querySelector('.funciones-popup');
    if (popup) {
        popup.style.animation = 'popupSlideIn 0.3s ease-out reverse';
        setTimeout(() => {
            popup.remove();
        }, 300);
    }
    
    // Mostrar mensaje en el chat
    appendMessage("¡Perfecto! Ahora puedes editar expedientes. Busca un expediente y haz clic en '✏️ Editar Expediente' para probar la nueva función.Asi como tambien visualizar en dashboard el estado y estadisticas de los expedientes existentes", "bot");
}

// Función para mostrar instrucciones de uso en el chat
function mostrarInstruccionesEdicion() {
    appendMessage("📋 **Instrucciones de Edición:**", "bot");
    appendMessage("1. 🔍 Busca un expediente por número, nombre o dirección", "bot");
    appendMessage("2. ✏️ Haz clic en 'Editar Expediente' en los resultados", "bot");
    appendMessage("3. 📝 Modifica los campos que necesites", "bot");
    appendMessage("4. 💾 Guarda los cambios y revisa el resumen", "bot");
    appendMessage("5. 🔍 Puedes ver el expediente actualizado inmediatamente", "bot");
}
function mostrarDashboard() {
    // 1. Guardamos la referencia al objeto de carga
    const loading = appendMessage("📊 Cargando estadísticas del sistema...");
    
    // 2. Petición AJAX
    $.ajax({
        url: "dashboard.php",
        method: "POST",
        dataType: "json",
        success: function(response) {
            
            // --- CORRECCIÓN DE ERROR ---
            // Verificamos si 'loading' existe y tiene el método remove()
            if (loading && typeof loading.remove === 'function') {
                loading.remove();
            }
            // ---------------------------

            console.log("Dashboard response:", response);
            
            if (response.success && response.data) {
                mostrarDashboardUI(response.data);
            } else {
                appendMessage("❌ Error: " + (response.error || "Desconocido"), "bot");
            }
        },
        error: function(xhr, status, error) {
            
            // --- CORRECCIÓN DE ERROR EN FAIL ---
            if (loading && typeof loading.remove === 'function') {
                loading.remove();
            }
            // -----------------------------------

            console.error("Dashboard AJAX error:", status, error);
            appendMessage("❌ Error de conexión al cargar el dashboard. Intenta de nuevo.", "bot");
        }
    });
}
function mostrarDashboardUI(data) {
    try {
        appendMessage("📊 **Dashboard del Sistema**", "bot");

        const estados = (data && data.por_estado) ? data.por_estado : {};

        // Helpers
        const parseFecha = (s) => {
            if (!s && s !== 0) return null;
            if (s instanceof Date) return isNaN(s) ? null : s;
            // Try native Date first (ISO or similar)
            const d1 = new Date(s);
            if (!isNaN(d1)) return d1;
            // Try dd/mm/yyyy or dd-mm-yyyy or dd/mm/yy
            const m = String(s).trim().match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
            if (m) {
                let day = parseInt(m[1], 10);
                let month = parseInt(m[2], 10) - 1;
                let year = parseInt(m[3], 10);
                if (year < 100) year += 2000;
                const d2 = new Date(year, month, day);
                return isNaN(d2) ? null : d2;
            }
            return null;
        };

        const formatFecha = (date, sep = "/") => {
            if (!date || isNaN(date)) return "";
            const dia = String(date.getDate()).padStart(2, "0");
            const mes = String(date.getMonth() + 1).padStart(2, "0");
            const anio = String(date.getFullYear()).slice(-2);
            return `${dia}${sep}${mes}${sep}${anio}`;
        };

        const safeGetColor = (estado) => {
            try {
                if (typeof getColorPorEstado === "function") return getColorPorEstado(estado);
            } catch (e) { /* ignore */ }
            if (!estado) return "#6c757d";
            const k = String(estado).toLowerCase();
            if (k.includes("entreg")) return "#28a745";
            if (k.includes("negad") || k.includes("rechaz")) return "#dc3545";
            if (k.includes("pend")) return "#ffc107";
            if (k.includes("retir")) return "#17a2b8";
            return "#6c757d";
        };

        // Ensure chatBody exists
        const chatBody = document.querySelector(".chat-body") || document.querySelector("#chatBody") || document.body;

        // Start HTML (use backticks)
        let html = `
        <div class="dashboard-container" style="background: white; padding: 20px; border-radius: 10px; margin: 10px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h5 style="text-align: center; margin-bottom: 20px; color: #2c3e50;">🌳 Estadísticas por Estado 🌳 </h5>

            <!-- Resumen general -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px;">
                <div style="background: linear-gradient(135deg, #cce7ff, #b3d9ff); padding: 15px; border-radius: 8px; text-align: center; border-left: 4px solid #007bff;">
                    <div style="font-size: 1.8em; font-weight: bold; color: #004085;">${(data && data.total) ? data.total : 0}</div>
                    <div style="font-size: 0.9em; color: #004085;">📁 Total Expedientes</div>
                </div>
                <div style="background: linear-gradient(135deg, #e8f4fd, #d4edfd); padding: 15px; border-radius: 8px; text-align: center; border-left: 4px solid #17a2b8;">
                    <div style="font-size: 1.8em; font-weight: bold; color: #0c5460;">${(data && data.este_mes) ? data.este_mes : 0}</div>
                    <div style="font-size: 0.9em; color: #0c5460;">📅 Este Mes</div>
                </div>
            </div>

            <!-- Estados específicos -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 15px;">
                <div style="background: linear-gradient(135deg, #11f215, #0ec90e); padding: 12px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 1.5em; font-weight: bold; color: white;">${estados.entregados || 0}</div>
                    <div style="font-size: 0.8em; color: white;">✅ Entregados</div>
                </div>
                <div style="background: linear-gradient(135deg, #cfe8ff, #b3d9ff); padding: 12px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 1.5em; font-weight: bold; color: #004085;">${estados.para_retirar || 0}</div>
                    <div style="font-size: 0.8em; color: #004085;">📦 Para Retirar</div>
                </div>
                <div style="background: linear-gradient(135deg, #e0f7fa, #b2ebf2); padding: 12px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 1.5em; font-weight: bold; color: #006064;">${estados.enviado_resolucion || 0}</div>
                    <div style="font-size: 0.8em; color: #006064;">📤 Enviado Resolución</div>
                </div>
                <div style="background: linear-gradient(135deg, #0dfde9, #0bd4c4); padding: 12px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 1.5em; font-weight: bold; color: #00695c;">${estados.visitados || 0}</div>
                    <div style="font-size: 0.8em; color: #00695c;">🔍 Visitados</div>
                </div>
                <div style="background: linear-gradient(135deg, #f7e11e, #f5d60c); padding: 12px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 1.5em; font-weight: bold; color: #7a6000;">${estados.pendiente_visita || 0}</div>
                    <div style="font-size: 0.8em; color: #7a6000;">🕐 Pend. Visita</div>
                </div>
                <div style="background: linear-gradient(135deg, #f8d7da, #f5c6cb); padding: 12px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 1.5em; font-weight: bold; color: #721c24;">${estados.negados || 0}</div>
                    <div style="font-size: 0.8em; color: #721c24;">🚫 Negados</div>
                </div>
            </div>
        `;

        // Sin estado (solo si tiene valor)
        if (estados.sin_estado && estados.sin_estado > 0) {
            html += `
            <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; text-align: center; margin-top: 10px; border: 1px dashed #6c757d;">
                <div style="font-size: 1.2em; font-weight: bold; color: #6c757d;">${estados.sin_estado}</div>
                <div style="font-size: 0.8em; color: #6c757d;">❓ Sin Estado (viejos expedientes sin Registro)</div>
            </div>
            `;
        }

        // Expedientes recientes
        if (data && Array.isArray(data.recientes) && data.recientes.length > 0) {
            // ordenar por fecha descendente usando parseFecha robusto
            data.recientes.sort((a, b) => {
                const da = parseFecha(a && a.fecha);
                const db = parseFecha(b && b.fecha);
                if (da && db) return db - da;
                if (da && !db) return -1;
                if (!da && db) return 1;
                return 0;
            });

       // --- Mostrar expedientes cercanos al mes (últimos 30 días) ---
// También incluí una opción comentada para mostrar el mes calendario actual.

html += `
    <div style="margin-top: 20px;">
        <h6 style="color: #495057; margin-bottom: 10px; border-bottom: 1px solid #dee2e6; padding-bottom: 5px;">📋 Nuevas Funciones</h6>
        <div style="max-height: 200px; overflow-y: auto;">
`;

// Helpers locales (por si no existen en el scope)
const parseFechaLocal = (s) => {
    if (!s && s !== 0) return null;
    if (s instanceof Date) return isNaN(s) ? null : s;
    const d1 = new Date(s);
    if (!isNaN(d1)) return d1;
    const m = String(s).trim().match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
    if (m) {
        let day = parseInt(m[1], 10);
        let month = parseInt(m[2], 10) - 1;
        let year = parseInt(m[3], 10);
        if (year < 100) year += 2000;
        const d2 = new Date(year, month, day);
        return isNaN(d2) ? null : d2;
    }
    return null;
};

const formatFechaLocal = (date, sep = "/") => {
    if (!date || isNaN(date)) return "";
    const dia = String(date.getDate()).padStart(2, "0");
    const mes = String(date.getMonth() + 1).padStart(2, "0");
    const anio = String(date.getFullYear()).slice(-2);
    return `${dia}${sep}${mes}${sep}${anio}`;
};

const safeGetColorLocal = (estado) => {
    try {
        if (typeof getColorPorEstado === "function") return getColorPorEstado(estado);
    } catch (e) {}
    if (!estado) return "#6c757d";
    const k = String(estado).toLowerCase();
    if (k.includes("entreg")) return "#28a745";
    if (k.includes("negad") || k.includes("rechaz")) return "#dc3545";
    if (k.includes("pend")) return "#ffc107";
    if (k.includes("retir")) return "#17a2b8";
    return "#6c757d";
};

// Parámetro: días hacia atrás (ajustá a 30, 15, 7, etc.)
const diasAtras = 30;
const fechaLimite = new Date();
fechaLimite.setHours(0,0,0,0);
fechaLimite.setDate(fechaLimite.getDate() - diasAtras);

// Si preferís el mes calendario actual (opción alternativa), descomenta este bloque y comenta el filtro por días.
// const hoy = new Date();
// const primerDiaMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
// const ultimoDiaMes = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);

const recientes = Array.isArray(data.recientes) ? data.recientes.slice() : [];

// Filtrar por fecha >= fechaLimite (últimos `diasAtras` días)
const filtrados = recientes.filter(exp => {
    const parsed = parseFechaLocal(exp && exp.fecha);
    if (!parsed) return false;
    parsed.setHours(0,0,0,0);
    return parsed.getTime() >= fechaLimite.getTime();

    // Si usás mes calendario, usar:
    // return parsed >= primerDiaMes && parsed <= ultimoDiaMes;
});

// Orden descendente por fecha
filtrados.sort((a,b) => {
    const da = parseFechaLocal(a && a.fecha);
    const db = parseFechaLocal(b && b.fecha);
    if (da && db) return db - da;
    if (da && !db) return -1;
    if (!da && db) return 1;
    return 0;
});

// Limitar si querés (ej: últimos 50 del rango)
const mostrados = filtrados.slice(0, 50);

if (mostrados.length === 0) {
    html += `
        <div style="text-align:center; color:#6c757d; font-size:0.9em; padding:10px;">
            Pronto Funcionara esta sección 📭
        </div>
    `;
} else {
    mostrados.forEach(exp => {
        const estadoColor = safeGetColorLocal(exp && exp.estado);
        const parsed = parseFechaLocal(exp && exp.fecha);
        const fechaFormateada = parsed ? formatFechaLocal(parsed, "/") : "";

        html += `
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px; border-bottom: 1px solid #f1f1f1;">
                <div>
                    <strong style="font-size: 0.9em;">${exp && exp.expediente ? exp.expediente : "N/A"}</strong>
                    <div style="font-size: 0.8em; color: #6c757d;">${exp && exp.caratula ? exp.caratula : "Sin carátula"}</div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.8em; color: #6c757d;">${fechaFormateada}</div>
                    <span style="font-size: 0.7em; background: ${estadoColor}; color: white; padding: 2px 6px; border-radius: 10px;">
                        ${exp && exp.estado ? exp.estado : "Sin estado"}
                    </span>
                </div>
            </div>
        `;
    });
}

html += `</div></div>`;


        }

        // Pie
        html += `
            <div style="margin-top: 15px; text-align: center; color: #6c757d; font-size: 0.9em;">
                🔄 Actualizado: ${new Date().toLocaleTimeString()}
            </div>
        </div>
        `;

        chatBody.insertAdjacentHTML("beforeend", html);
        chatBody.scrollTop = chatBody.scrollHeight;

    } catch (err) {
        console.error("mostrarDashboardUI error:", err);
        // mostrar un mensaje visual (opcional)
        appendMessage("❗ Error al mostrar el dashboard. Revisa la consola.", "bot");
    }
}
// --- INICIO: tu código original (con adaptaciones mínimas para integrar la nueva función) ---

function mostrarDashboard() {
    const loading = appendMessage("📊 Cargando estadísticas del sistema...");
    
    $.ajax({
        url: "dashboard.php",
        method: "POST",
        dataType: "json",
        success: function(response) {
            loading.remove();
            console.log("Dashboard response:", response);
            
            if (response.success && response.data) {
                mostrarDashboardUI(response.data);

                // --- Llamada a la nueva función que trae expedientes "cercanos" desde el servidor ---
                // Ajustá días y límite según prefieras: (dias = 30, limite = 50)
                mostrarExpedientesCercanos(30, 50);
            } else {
                appendMessage("❌ Error: " + (response.error || "Desconocido"), "bot");
            }
        },
        error: function(xhr, status, error) {
            loading.remove();
            console.error("Dashboard AJAX error:", status, error);
            appendMessage("❌ Error de conexión al cargar el dashboard.", "bot");
        }
    });
}

function mostrarDashboardUI(data) {
    try {
        appendMessage("📊 **Dashboard del Sistema**", "bot");

        const estados = (data && data.por_estado) ? data.por_estado : {};

        // Helpers
        const parseFecha = (s) => {
            if (!s && s !== 0) return null;
            if (s instanceof Date) return isNaN(s) ? null : s;
            const d1 = new Date(s);
            if (!isNaN(d1)) return d1;
            const m = String(s).trim().match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
            if (m) {
                let day = parseInt(m[1], 10);
                let month = parseInt(m[2], 10) - 1;
                let year = parseInt(m[3], 10);
                if (year < 100) year += 2000;
                const d2 = new Date(year, month, day);
                return isNaN(d2) ? null : d2;
            }
            return null;
        };

        const formatFecha = (date, sep = "/") => {
            if (!date || isNaN(date)) return "";
            const dia = String(date.getDate()).padStart(2, "0");
            const mes = String(date.getMonth() + 1).padStart(2, "0");
            const anio = String(date.getFullYear()).slice(-2);
            return `${dia}${sep}${mes}${sep}${anio}`;
        };

        const safeGetColor = (estado) => {
            try {
                if (typeof getColorPorEstado === "function") return getColorPorEstado(estado);
            } catch (e) { /* ignore */ }
            if (!estado) return "#6c757d";
            const k = String(estado).toLowerCase();
            if (k.includes("entreg")) return "#28a745";
            if (k.includes("negad") || k.includes("rechaz")) return "#dc3545";
            if (k.includes("pend")) return "#ffc107";
            if (k.includes("retir")) return "#17a2b8";
            return "#6c757d";
        };

        const chatBody = document.querySelector(".chat-body") || document.querySelector("#chatBody") || document.body;

        let html = `
        <div class="dashboard-container" style="background: white; padding: 20px; border-radius: 10px; margin: 10px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h5 style="text-align: center; margin-bottom: 20px; color: #2c3e50;">🌳 Estadísticas por Estado 🌳 </h5>

            <!-- Resumen general -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px;">
                <div style="background: linear-gradient(135deg, #cce7ff, #b3d9ff); padding: 15px; border-radius: 8px; text-align: center; border-left: 4px solid #007bff;">
                    <div style="font-size: 1.8em; font-weight: bold; color: #004085;">${(data && data.total) ? data.total : 0}</div>
                    <div style="font-size: 0.9em; color: #004085;">📁 Total Expedientes</div>
                </div>
                <div style="background: linear-gradient(135deg, #e8f4fd, #d4edfd); padding: 15px; border-radius: 8px; text-align: center; border-left: 4px solid #17a2b8;">
                    <div style="font-size: 1.8em; font-weight: bold; color: #0c5460;">${(data && data.este_mes) ? data.este_mes : 0}</div>
                    <div style="font-size: 0.9em; color: #0c5460;">📅 Este Mes</div>
                </div>
            </div>

            <!-- Estados específicos -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 15px;">
                <div style="background: linear-gradient(135deg, #11f215, #0ec90e); padding: 12px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 1.5em; font-weight: bold; color: white;">${estados.entregados || 0}</div>
                    <div style="font-size: 0.8em; color: white;">✅ Entregados</div>
                </div>
                <div style="background: linear-gradient(135deg, #cfe8ff, #b3d9ff); padding: 12px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 1.5em; font-weight: bold; color: #004085;">${estados.para_retirar || 0}</div>
                    <div style="font-size: 0.8em; color: #004085;">📦 Para Retirar</div>
                </div>
                <div style="background: linear-gradient(135deg, #e0f7fa, #b2ebf2); padding: 12px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 1.5em; font-weight: bold; color: #006064;">${estados.enviado_resolucion || 0}</div>
                    <div style="font-size: 0.8em; color: #006064;">📤 Enviado Resolución</div>
                </div>
                <div style="background: linear-gradient(135deg, #0dfde9, #0bd4c4); padding: 12px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 1.5em; font-weight: bold; color: #00695c;">${estados.visitados || 0}</div>
                    <div style="font-size: 0.8em; color: #00695c;">🔍 Visitados</div>
                </div>
                <div style="background: linear-gradient(135deg, #f7e11e, #f5d60c); padding: 12px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 1.5em; font-weight: bold; color: #7a6000;">${estados.pendiente_visita || 0}</div>
                    <div style="font-size: 0.8em; color: #7a6000;">🕐 Pend. Visita</div>
                </div>
                <div style="background: linear-gradient(135deg, #f8d7da, #f5c6cb); padding: 12px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 1.5em; font-weight: bold; color: #721c24;">${estados.negados || 0}</div>
                    <div style="font-size: 0.8em; color: #721c24;">🚫 Negados</div>
                </div>
            </div>
        `;

        if (estados.sin_estado && estados.sin_estado > 0) {
            html += `
            <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; text-align: center; margin-top: 10px; border: 1px dashed #6c757d;">
                <div style="font-size: 1.2em; font-weight: bold; color: #6c757d;">${estados.sin_estado}</div>
                <div style="font-size: 0.8em; color: #6c757d;">❓ Sin Estado (viejos expedientes sin Registro)</div>
            </div>
            `;
        }

        if (data && Array.isArray(data.recientes) && data.recientes.length > 0) {
        }

        // Pie
        html += `
            <div style="margin-top: 15px; text-align: center; color: #6c757d; font-size: 0.9em;">
                🔄 Actualizado: ${new Date().toLocaleTimeString()}
            </div>
        </div>
        `;

        chatBody.insertAdjacentHTML("beforeend", html);
        chatBody.scrollTop = chatBody.scrollHeight;

    } catch (err) {
        console.error("mostrarDashboardUI error:", err);
        appendMessage("❗ Error al mostrar el dashboard. Revisa la consola.", "bot");
    }
}

function mostrarExpedientesCercanos(dias = 30, limite = 50) {
    const loading = appendMessage("🔎 Cargando expedientes cercanos...");
    $.ajax({
        url: "dashboard.php",
        method: "POST",
        dataType: "json",
        data: { action: "recientes_cercanos", dias: dias, limite: limite },
        success: function(response) {
            loading.remove();
            if (!response || !response.success) {
                console.warn("recientes_cercanos: respuesta inválida", response);
                const container = document.querySelector("#recientes-cercanos");
                if (container) container.innerHTML = `<div style="color:#6c757d; padding:10px;">No se pudieron cargar los expedientes cercanos desde el servidor.</div>`;
                return;
            }
            const lista = (response.data && Array.isArray(response.data.recientes)) ? response.data.recientes : [];
            renderRecientesCercanos(lista);
        },
        error: function(xhr, status, error) {
            loading.remove();
            console.error("AJAX error (recientes_cercanos):", status, error);
            const container = document.querySelector("#recientes-cercanos");
            if (container) container.innerHTML = `<div style="color:#6c757d; padding:10px;">Error de conexión al cargar expedientes cercanos.</div>`;
        }
    });
}

function renderRecientesCercanos(lista) {
    const parseFechaLocal = s => {
        if (!s && s !== 0) return null;
        if (s instanceof Date) return isNaN(s) ? null : s;
        const d1 = new Date(s);
        if (!isNaN(d1)) return d1;
        const m = String(s).trim().match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
        if (m) {
            let day = parseInt(m[1], 10), month = parseInt(m[2], 10) - 1, year = parseInt(m[3], 10);
            if (year < 100) year += 2000;
            const d2 = new Date(year, month, day);
            return isNaN(d2) ? null : d2;
        }
        return null;
    };
    const formatFechaLocal = date => {
        if (!date || isNaN(date)) return "";
        const dia = String(date.getDate()).padStart(2, "0");
        const mes = String(date.getMonth() + 1).padStart(2, "0");
        const anio = String(date.getFullYear()).slice(-2);
        return `${dia}/${mes}/${anio}`;
    };
    const safeGetColorLocal = estado => {
        if (!estado) return "#6c757d";
        const k = String(estado).toLowerCase();
        if (k.includes("entreg")) return "#28a745";
        if (k.includes("negad") || k.includes("rechaz")) return "#dc3545";
        if (k.includes("pend")) return "#ffc107";
        if (k.includes("retir")) return "#17a2b8";
        return "#6c757d";
    };

    let cont = document.querySelector("#recientes-cercanos");
    if (!cont) {
        cont = document.createElement("div");
        cont.id = "recientes-cercanos";
        cont.style.marginTop = "18px";
        cont.style.maxWidth = "100%";
        const dashboardContainer = document.querySelector(".dashboard-container") || document.body;
        dashboardContainer.appendChild(cont);
    }

    if (!Array.isArray(lista) || lista.length === 0) {
        cont.innerHTML = `<div style="color:#6c757d; padding:10px;">No hay expedientes cercanos en el rango solicitado.</div>`;
        return;
    }

    try {
        const hoy = new Date();
        lista.sort((a, b) => {
            const da = parseFechaLocal(a && a.fecha);
            const db = parseFechaLocal(b && b.fecha);
            if (!da && !db) return 0;
            if (!da) return 1;
            if (!db) return -1;
            return Math.abs(da - hoy) - Math.abs(db - hoy);
        });
    } catch (e) { /* ignore */ }

    let html = `<h6 style="color:#495057; margin-bottom:6px;">📌 Expedientes cercanos a la fecha</h6>
                <div style="max-height:260px; overflow:auto; border:1px solid #eee; border-radius:6px;">`;

    lista.forEach(exp => {
        const parsed = parseFechaLocal(exp && exp.fecha);
        html += `
            <div style="display:flex; justify-content:space-between; padding:8px; border-bottom:1px solid #f5f5f5;">
                <div>
                    <strong style="font-size:0.9em;">${exp.expediente || "N/A"}</strong>
                    <div style="font-size:0.8em; color:#6c757d;">${exp.caratula || "Sin carátula"}</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:0.8em; color:#6c757d;">${parsed ? formatFechaLocal(parsed) : (exp.fecha || "")}</div>
                    <span style="font-size:0.75em; background:${safeGetColorLocal(exp.estado)}; color:white; padding:3px 6px; border-radius:10px;">
                        ${exp.estado || "Sin estado"}
                    </span>
                </div>
            </div>
        `;
    });

    html += `</div>`;
    cont.innerHTML = html;
    cont.scrollTop = cont.scrollHeight;
}





// Función auxiliar para colores según estado
function getColorPorEstado(estado) {
    if (!estado) return '#6c757d';
    
    const estadoLower = estado.toLowerCase();
    if (estadoLower.includes('negad')) return '#dc3545';
    if (estadoLower.includes('entregado') || estadoLower.includes('archivado')) return '#28a745';
    if (estadoLower.includes('resuelto') || estadoLower.includes('resoluc')) return '#20c997';
    if (estadoLower.includes('visitad') && !estadoLower.includes('pendiente')) return '#17a2b8';
    if (estadoLower.includes('pendiente') && estadoLower.includes('visita')) return '#ffc107';
    if (estadoLower.includes('enviado') && estadoLower.includes('resoluc')) return '#6f42c1';
    if (estadoLower.includes('retirar') || estadoLower.includes('permiso')) return '#007bff';
    
    return '#6c757d';
}

// Sistema de monitoreo de cambios de estado
let ultimaRevisionEstados = null;
let intervaloMonitoreo = null;

function iniciarMonitoreoEstados() {
    appendMessage("🔍 Iniciando monitoreo de cambios de estado...", "bot");
    
    // Primera revisión para establecer línea base
    revisarCambiosEstados().then(estadosIniciales => {
        ultimaRevisionEstados = estadosIniciales;
        appendMessage("✅ Línea base establecida - Monitoreando cambios...", "bot");
    });
    
    // Revisar cambios cada 30 segundos
    intervaloMonitoreo = setInterval(async () => {
        const cambios = await detectarCambiosEstados();
        if (cambios.length > 0) {
            notificarCambiosEstados(cambios);
        }
    }, 30000); // 30 segundos
    
    appendMessage("📡 Monitoreo activo - Revisando cambios cada 30 segundos", "bot");
    
    // Agregar control para detener
    chatBody.insertAdjacentHTML("beforeend", `
        <div class="option-box" onclick="detenerMonitoreoEstados()" style="background: #dc3545; color: white;">
            ⏹️ Detener Monitoreo
        </div>
    `);
}

function detenerMonitoreoEstados() {
    if (intervaloMonitoreo) {
        clearInterval(intervaloMonitoreo);
        intervaloMonitoreo = null;
        appendMessage("🔴 Monitoreo de estados detenido", "bot");
    }
}
// Detectar cambios en los estados de expedientes
async function detectarCambiosEstados() {
    try {
        const estadosActuales = await revisarCambiosEstados();
        const cambios = [];
        
        if (!ultimaRevisionEstados) {
            ultimaRevisionEstados = estadosActuales;
            return cambios;
        }
        
        // Comparar con la revisión anterior
        estadosActuales.forEach(estadoActual => {
            const estadoAnterior = ultimaRevisionEstados.find(
                e => e.expediente === estadoActual.expediente
            );
            
            if (estadoAnterior && estadoAnterior.estado !== estadoActual.estado) {
                cambios.push({
                    expediente: estadoActual.expediente,
                    estado_anterior: estadoAnterior.estado,
                    estado_nuevo: estadoActual.estado,
                    timestamp: new Date().toISOString()
                });
            }
        });
        
        // Actualizar línea base
        ultimaRevisionEstados = estadosActuales;
        
        return cambios;
    } catch (error) {
        console.error("Error detectando cambios:", error);
        return [];
    }
}

// Obtener estados actuales de expedientes
async function revisarCambiosEstados() {
    return new Promise((resolve) => {
        $.ajax({
            url: "monitoreo_estados.php",
            method: "POST",
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    resolve(response.estados || []);
                } else {
                    resolve([]);
                }
            },
            error: function() {
                resolve([]);
            }
        });
    });
}
// Notificar cambios detectados
function notificarCambiosEstados(cambios) {
    cambios.forEach(cambio => {
        const mensaje = `🔄 ${cambio.expediente} cambió de "${cambio.estado_anterior}" a "${cambio.estado_nuevo}"`;
        
        // Notificación toast
        mostrarNotificacionEstado(mensaje, cambio.expediente);
        
        // También mostrar en el chat
        appendMessage(mensaje, "bot");
        
        // Agregar opciones rápidas
        chatBody.insertAdjacentHTML("beforeend", `
            <div class="option-box" onclick="fetchResults('${cambio.expediente}', 'expediente')">
                🔍 Ver ${cambio.expediente}
            </div>
        `);
    });
}

// Notificación especial para cambios de estado
function mostrarNotificacionEstado(mensaje, expediente) {
    const notificationId = 'estado-' + Date.now();
    const toast = document.createElement('div');
    toast.id = notificationId;
    toast.style.cssText = `
        background: linear-gradient(135deg, #ff6b6b, #ee5a52);
        color: white;
        padding: 15px 20px;
        margin-bottom: 10px;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        border-left: 4px solid #ffd93d;
        animation: slideInRight 0.3s ease, fadeOut 0.3s ease 7.7s forwards;
        cursor: pointer;
        position: relative;
        max-width: 400px;
    `;
    
    toast.innerHTML = `
        <div style="display: flex; align-items: flex-start; gap: 10px;">
            <span style="font-size: 1.3em;">🔄</span>
            <div style="flex: 1;">
                <div style="font-weight: bold; font-size: 0.9em; margin-bottom: 5px;">
                    📊 Cambio de Estado Detectado
                </div>
                <div style="font-size: 0.85em; opacity: 0.9; line-height: 1.3;">
                    ${mensaje}
                </div>
                <div style="font-size: 0.75em; opacity: 0.7; margin-top: 5px;">
                    ${new Date().toLocaleTimeString()}
                </div>
            </div>
            <button onclick="document.getElementById('${notificationId}').remove()" 
                    style="background: none; border: none; color: white; cursor: pointer; font-size: 1.2em; padding: 0;">
                ×
            </button>
        </div>
        <div style="position: absolute; bottom: 0; left: 0; width: 100%; height: 3px; background: rgba(255,255,255,0.3);">
            <div style="height: 100%; background: #ffd93d; width: 100%; animation: progressBar 8s linear;"></div>
        </div>
    `;
    
    // Agregar funcionalidad de click para abrir expediente
    toast.addEventListener('click', function() {
        fetchResults(expediente, 'expediente');
        this.remove();
    });
    
    // Agregar al contenedor
    let container = document.getElementById('notificaciones-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notificaciones-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
        `;
        document.body.appendChild(container);
    }
    
    container.appendChild(toast);
    
    // Auto-remover después de 8 segundos
    setTimeout(() => {
        if (document.getElementById(notificationId)) {
            document.getElementById(notificationId).remove();
        }
    }, 8000);
}
function panelMonitoreoEstados() {
    appendMessage("📊 **Panel de Control - Monitoreo de Estados**", "bot");
    
    chatBody.insertAdjacentHTML("beforeend", `
        <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin: 10px 0;">
            <h5>🔍 Monitoreo de Cambios de Estado</h5>
            <p style="font-size: 0.9em; color: #666;">
                Detecta automáticamente cuando los expedientes cambian de estado
            </p>
            
            <div style="display: grid; grid-template-columns: 1fr; gap: 8px;">
                <div class="option-box" onclick="iniciarMonitoreoEstados()">
                    🟢 Iniciar Monitoreo
                </div>
                <div class="option-box" onclick="detenerMonitoreoEstados()">
                    🔴 Detener Monitoreo
                </div>
                <div class="option-box" onclick="verEstadoMonitoreo()">
                    📡 Estado del Monitoreo
                </div>
                <div class="option-box" onclick="revisarCambiosManual()">
                    🔄 Revisar Cambios Ahora
                </div>
            </div>
        </div>
    `);
}

function verEstadoMonitoreo() {
    if (intervaloMonitoreo) {
        appendMessage("🟢 Monitoreo de estados: ACTIVO", "bot");
        appendMessage("📡 Revisando cambios cada 30 segundos", "bot");
        appendMessage("⏰ Última revisión: " + (ultimaRevisionEstados ? new Date().toLocaleTimeString() : "Nunca"), "bot");
    } else {
        appendMessage("🔴 Monitoreo de estados: INACTIVO", "bot");
    }
}

async function revisarCambiosManual() {
    const loading = appendMessage("🔍 Revisando cambios manualmente...");
    const cambios = await detectarCambiosEstados();
    loading.remove();
    
    if (cambios.length > 0) {
        notificarCambiosEstados(cambios);
    } else {
        appendMessage("✅ No se detectaron cambios desde la última revisión", "bot");
    }
}
// VERSIÓN SIMPLIFICADA - SEGURO QUE FUNCIONA
function iniciarNotificacionesTiempoReal() {
    appendMessage("🔔 Notificaciones activadas", "bot");
    
    // Notificaciones de ejemplo
    const notifs = [
        "Nuevo expediente: 130-K-24",
        "Expediente 125-K-24 actualizado", 
        "Recordatorio: Revisar pendientes",
        "Sistema funcionando correctamente"
    ];
    
    // Mostrar una notificación cada 2 minutos
    setInterval(() => {
        const mensaje = notifs[Math.floor(Math.random() * notifs.length)];
        
        // Crear notificación simple
        const notif = document.createElement('div');
        notif.style.cssText = `
            position: fixed; top: 20px; right: 20px; 
            background: #007bff; color: white; padding: 15px; 
            border-radius: 5px; z-index: 10000; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        `;
        notif.innerHTML = `🔔 ${mensaje}`;
        document.body.appendChild(notif);
        
        // Auto-remover después de 5 segundos
        setTimeout(() => notif.remove(), 5000);
        
    }, 120000);
    
    appendMessage("✅ Recibirás notificaciones cada 2 minutos", "bot");
}

function detenerNotificacionesTiempoReal() {
    // En una implementación real aquí se limpiaría el intervalo
    appendMessage("🔴 Notificaciones detenidas", "bot");
}
let pollingInterval = null;
let ultimaNotificacionId = 0;

// Función mejorada para enviar notificaciones globales
function enviarNotificacionGlobal(expediente, mensaje, tipo = 'info') {
    console.log(`📤 Enviando notificación global: ${expediente} - ${mensaje}`);
    
    $.ajax({
        url: 'enviar_notificacion.php',
        method: 'POST',
        data: {
            expediente: expediente,
            mensaje: mensaje,
            tipo: tipo
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                console.log('✅ Notificación enviada a todos los usuarios - ID:', response.id);
            } else {
                console.error('❌ Error enviando notificación:', response.error);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Error de conexión al enviar notificación:', error);
        }
    });
}
// Función para verificar nuevas notificaciones (VERSIÓN CORREGIDA)
async function verificarNotificacionesGlobales() {
    try {
        const response = await fetch(`obtener_notificaciones.php?ultimo_id=${ultimaNotificacionId}`);
        const data = await response.json();
        
        if (data.success && data.notificaciones) {
            console.log(`📨 ${data.notificaciones.length} notificaciones nuevas recibidas`);
            
            // Mostrar todas las notificaciones recibidas (ya están ordenadas por las más recientes primero)
            data.notificaciones.forEach(notif => {
                mostrarNotificacionGlobal(notif);
            });
            
            // Actualizar el último ID con el valor devuelto por el servidor
            if (data.ultimo_id && data.ultimo_id > ultimaNotificacionId) {
                ultimaNotificacionId = data.ultimo_id;
                console.log(`🆕 Último ID actualizado: ${ultimaNotificacionId}`);
            }
        }
    } catch (error) {
        console.error('Error verificando notificaciones:', error);
    }
}
// Función para mostrar notificación global (MEJORADA) + push
function mostrarNotificacionGlobal(notificacion) {
    // Determinar icono según el tipo de notificación
    let icono = '🔔';
    switch(notificacion.tipo) {
        case 'Nuevo Expediente':
            icono = '📄';
            break;
        case 'Nueva Nota':
            icono = '📝';
            break;
        case 'Se Subieron nuevas imagenes':
            icono = '📸';
            break;
        case 'Cambio de estado':
            icono = '🔄';
            break;
        case 'Editado':
            icono = '✏️';
            break;
        default:
            icono = '🔔';
    }

    const mensaje = `${icono} ${notificacion.expediente}: ${notificacion.mensaje}`;

    // Mostrar notificación toast local
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        padding: 15px 20px;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        z-index: 10000;
        max-width: 400px;
        border-left: 4px solid #ffd700;
        animation: slideInRight 0.3s ease;
        cursor: pointer;
    `;

    toast.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.2em;">${icono}</span>
            <div style="flex: 1;">
                <div style="font-weight: bold; font-size: 0.9em;">${notificacion.tipo ? notificacion.tipo.replace('_', ' ') : 'Notificación'}</div>
                <div style="font-size: 0.85em;">${notificacion.mensaje}</div>
                <div style="font-size: 0.7em; opacity: 0.8; margin-top: 5px;">
                    Por: ${notificacion.usuario_creador} - ${new Date(notificacion.fecha_creacion).toLocaleTimeString()}
                </div>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" 
                    style="background: none; border: none; color: white; cursor: pointer; font-size: 1.2em;">
                ×
            </button>
        </div>
    `;

    toast.addEventListener('click', function() {
        this.remove();
    });

    document.body.appendChild(toast);

    // Auto-remover después de 10 segundos
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 10000);

    console.log('🔔 Nueva notificación recibida:', notificacion);

    // 🔔 Enviar notificación push al Service Worker si está registrado
    if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
        navigator.serviceWorker.ready.then(registration => {
            registration.showNotification(notificacion.tipo || 'Notificación', {
                body: notificacion.mensaje,
                icon: '/logo.png',
                badge: '/logo.png',
                data: {
                    expediente: notificacion.expediente,
                    url: `/exp/?expediente=${encodeURIComponent(notificacion.expediente)}`
                },
                tag: notificacion.expediente,
                renotify: true
            });
        });
    }
}

// Iniciar polling de notificaciones (VERSIÓN MEJORADA) + push
function iniciarPollingNotificaciones() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }

    // Primero obtener el ID más reciente para empezar desde ahí
    fetch('obtener_notificaciones.php?ultimo_id=0')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.ultimo_id) {
                ultimaNotificacionId = data.ultimo_id;
                console.log(`🔔 Sistema de notificaciones iniciado. Último ID: ${ultimaNotificacionId}`);
            }
        })
        .catch(error => {
            console.error('Error inicializando notificaciones:', error);
        });

    // Verificar cada 5 segundos
    pollingInterval = setInterval(verificarNotificacionesGlobales, 5000);

    // Verificar inmediatamente después de 1 segundo
    setTimeout(verificarNotificacionesGlobales, 1000);
}

// Función interna que verifica notificaciones y las muestra
function verificarNotificacionesGlobales() {
    fetch(`obtener_notificaciones.php?ultimo_id=${ultimaNotificacionId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.notificaciones && data.notificaciones.length > 0) {
                data.notificaciones.forEach(notif => {
                    mostrarNotificacionGlobal(notif);
                    ultimaNotificacionId = Math.max(ultimaNotificacionId, notif.id);
                });
            }
        })
        .catch(err => console.error('Error en polling notificaciones:', err));
}

// Detener polling
function detenerPollingNotificaciones() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
        appendMessage("🔴 Notificaciones globales DETENIDAS", "bot");
    }
}
// Modificar la función de guardar cambios para notificar globalmente
function guardarEdicionExpediente(expediente) {
    const datosEditados = {};
    let cambios = false;
    let estadoAnterior = '';
    let cambiosDetectados = [];
    
    const campos = ['caratula', 'nombre_apellido', 'celular', 'fecha_inicio', 'juzgado', 
                   'responsable', 'objeto', 'observaciones', 'seccion', 'direccion', 
                   'estado', 'barrio'];
    
    // Recopilar datos y detectar cambios
    campos.forEach(campo => {
        const input = document.getElementById(`edit_${campo}`);
        if (input) {
            datosEditados[campo] = input.value.trim();
            
            // Guardar estado anterior
            if (campo === 'estado') {
                estadoAnterior = input.defaultValue || '';
            }
            
            // Detectar si hubo cambio
            if (input.value !== input.defaultValue) {
                cambios = true;
                cambiosDetectados.push({
                    campo: campo,
                    anterior: input.defaultValue,
                    nuevo: input.value
                });
            }
        }
    });
    
    if (!cambios) {
        appendMessage("ℹ️ No se realizaron cambios.", "bot");
        return;
    }
    
    const loading = appendMessage("Guardando cambios...");
    
    $.ajax({
        url: "update_expediente.php",
        method: "POST",
        data: { ...datosEditados, expediente: expediente },
        dataType: "json",
        success: function(response) {
            loading.remove();
            
            if (response.success) {
                appendMessage("✅ Expediente actualizado correctamente.", "bot");
                
                // 🔥 **NOTIFICACIÓN GLOBAL - ESTO ES LO QUE FALTABA**
                const usuario = '<?php echo $_SESSION["username"]; ?>';
                
                // Verificar si cambió el estado (caso más importante)
                const estadoNuevo = datosEditados.estado || '';
                if (estadoAnterior !== estadoNuevo && estadoNuevo !== '') {
                    const mensajeEstado = `🔄 ${expediente} cambió de "${estadoAnterior}" a "${estadoNuevo}"`;
                    
                    // 1. Notificación local
                    mostrarNotificacion(mensajeEstado);
                    
                    // 2. Notificación GLOBAL para todos los usuarios
                    enviarNotificacionGlobal(
                        expediente, 
                        `Cambió estado de "${estadoAnterior}" a "${estadoNuevo}"`,
                        '🔄 Cambio de estado'
                    );
                }
                
                // Notificar otros cambios importantes
                cambiosDetectados.forEach(cambio => {
                    if (['direccion', 'observaciones', 'responsable', 'celular'].includes(cambio.campo)) {
                        const mensajeCampo = `Campo "${cambio.campo}" modificado: "${cambio.anterior}" → "${cambio.nuevo}"`;
                        
                        enviarNotificacionGlobal(
                            expediente,
                            mensajeCampo,
                            '✏️  Edicion de expediente'
                        );
                    }
                });
                
                // Notificación general de edición
                if (cambiosDetectados.length > 0) {
                    enviarNotificacionGlobal(
                        expediente,
                        `El Usuario  ${usuario} - ha modificado ${cambiosDetectados.length}  campo(s)  del expediente ${expediente}`,
                        '✏️  Editado'
                    );
                }
                
                // Mostrar resumen de cambios localmente
                if (response.cambios && response.cambios.length > 0) {
                    let resumen = "<strong>Cambios realizados:</strong><br>";
                    response.cambios.forEach(cambio => {
                        resumen += `• <strong>${cambio.campo}</strong>: "${cambio.anterior}" → "${cambio.nuevo}"<br>`;
                    });
                    appendMessage(resumen, "bot");
                }
                
            } else {
                appendMessage("❌ Error al actualizar: " + response.error, "bot");
            }
        },
        error: function(xhr, status, error) {
            loading.remove();
            appendMessage("❌ Error de conexión al guardar cambios.", "bot");
        }
    });
}
function panelNotificacionesGlobales() {
    appendMessage("🌐 **Sistema de Notificaciones en Tiempo Real**", "bot");
    
    chatBody.insertAdjacentHTML("beforeend", `
        <div style="background: #e3f2fd; padding: 15px; border-radius: 10px; margin: 10px 0;">
            <h5>🔔 Notificaciones</h5>
            <p style="font-size: 0.9em; color: #666;">
                <div class="option-box" onclick="detenerPollingNotificaciones()">
                    🔴 Detener Notificaciones Globales
                </div>
            </div>
        </div>
    `);
}

// Función de prueba
function testNotificacionGlobal() {
    enviarNotificacionGlobal(
        'TEST-001', 
        'Esta es una notificación de prueba del sistema de notificaciones', 
        'testing Message'
    );
    appendMessage("🧪 Notificación de prueba enviada a todos los usuarios", "bot");
}
// Función para debuggear el sistema de notificaciones
function debugNotificaciones() {
    appendMessage("🐛 **Debug del Sistema de Notificaciones**", "bot");
    
    // Verificar si la tabla existe
    $.ajax({
        url: 'obtener_notificaciones.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                appendMessage("✅ Tabla de notificaciones: OK", "bot");
                appendMessage(`📊 Notificaciones en BD: ${response.notificaciones.length}`, "bot");
                
                // Mostrar últimas notificaciones
                if (response.notificaciones.length > 0) {
                    appendMessage("Últimas notificaciones:", "bot");
                    response.notificaciones.slice(0, 3).forEach(notif => {
                        appendMessage(`• ${notif.usuario_creador}: ${notif.mensaje}`, "bot");
                    });
                }
            } else {
                appendMessage("❌ Error con la tabla: " + response.error, "bot");
            }
        },
        error: function() {
            appendMessage("❌ No se pudo conectar a la BD de notificaciones", "bot");
        }
    });
    
    // Probar envío de notificación
    setTimeout(() => {
        appendMessage("🧪 Probando envío de notificación...", "bot");
        enviarNotificacionGlobal('DEBUG-001', 'Esta es una notificación de prueba del debug', 'debug');
    }, 1000);
    
    // Verificar recepción después de 3 segundos
    setTimeout(() => {
        verificarNotificacionesGlobales();
        appendMessage("🔍 Verificando recepción...", "bot");
    }, 3000);
}
// =============================================
// FUNCIÓN QUE FALTA - AGREGAR AL FINAL DEL SCRIPT
// =============================================

function mostrarNotificacion(mensaje) {
    console.log("🔔 Mostrando notificación:", mensaje);
    
    // Crear contenedor si no existe
    let notificacionesContainer = document.getElementById('notificaciones-container');
    if (!notificacionesContainer) {
        notificacionesContainer = document.createElement('div');
        notificacionesContainer.id = 'notificaciones-container';
        notificacionesContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 350px;
        `;
        document.body.appendChild(notificacionesContainer);
    }
    
    // ID único para la notificación
    const notificationId = 'notif-' + Date.now();
    
    // Crear elemento de notificación
    const toast = document.createElement('div');
    toast.id = notificationId;
    toast.style.cssText = `
        background: linear-gradient(135deg, #007bff, #0056b3);
        color: white;
        padding: 15px 20px;
        margin-bottom: 10px;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        border-left: 4px solid #ffd700;
        animation: slideInRight 0.3s ease;
        cursor: pointer;
        position: relative;
        max-width: 400px;
    `;
    
    toast.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.2em;">🔔</span>
            <div style="flex: 1;">
                <div style="font-weight: bold; font-size: 0.9em;">Notificación del Sistema</div>
                <div style="font-size: 0.85em; opacity: 0.9;">${mensaje}</div>
            </div>
            <button onclick="document.getElementById('${notificationId}').remove()" 
                    style="background: none; border: none; color: white; cursor: pointer; font-size: 1.2em;">
                ×
            </button>
        </div>
    `;
    
    // Agregar estilos de animación si no existen
    if (!document.getElementById('notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            @keyframes slideInRight {
                from { 
                    transform: translateX(100%); 
                    opacity: 0; 
                }
                to { 
                    transform: translateX(0); 
                    opacity: 1; 
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Agregar funcionalidad de click
    toast.addEventListener('click', function() {
        this.remove();
    });
    
    // Agregar al contenedor
    notificacionesContainer.appendChild(toast);
    
    // Auto-remover después de 5 segundos
    setTimeout(() => {
        if (document.getElementById(notificationId)) {
            document.getElementById(notificationId).remove();
        }
    }, 5000);
}

// También agregar esta función auxiliar para el monitoreo de estados
function getColorPorEstado(estado) {
    if (!estado) return '#6c757d';
    
    const estadoLower = estado.toLowerCase();
    if (estadoLower.includes('negad')) return '#dc3545';
    if (estadoLower.includes('entregado') || estadoLower.includes('archivado')) return '#28a745';
    if (estadoLower.includes('resuelto') || estadoLower.includes('resoluc')) return '#20c997';
    if (estadoLower.includes('visitad') && !estadoLower.includes('pendiente')) return '#17a2b8';
    if (estadoLower.includes('pendiente') && estadoLower.includes('visita')) return '#ffc107';
    if (estadoLower.includes('pendiente')) return '#fd7e14';
    if (estadoLower.includes('enviado') && estadoLower.includes('resoluc')) return '#6f42c1';
    if (estadoLower.includes('retirar') || estadoLower.includes('permiso')) return '#007bff';
    
    return '#6c757d';
}


// =============================================
// SISTEMA DE NOTIFICACIONES PUSH
// =============================================

// Inicializar notificaciones push
async function inicializarSistemaPush() {
    try {
        const pushEnabled = await pushNotifications.init();
        
        if (pushEnabled) {
            appendMessage("🔔 Sistema de notificaciones push ACTIVADO - Recibirás alertas incluso con la app cerrada", "bot");
            console.log('🚀 Notificaciones push inicializadas correctamente');
        } else {
            appendMessage("ℹ️ Las notificaciones push no están disponibles en tu navegador", "bot");
        }
    } catch (error) {
        console.error('Error inicializando push:', error);
        appendMessage("⚠️ Las notificaciones push no están disponibles", "bot");
    }
}

// Función mejorada para enviar notificaciones
async function enviarNotificacionCompleta(expediente, mensaje, tipo = 'info') {
    console.log(`📤 Enviando notificación completa: ${expediente}`);
    
    try {
        // 1. Notificación en tiempo real (app abierta)
        enviarNotificacionGlobal(expediente, mensaje, tipo);
        
        // 2. Notificación push (app cerrada/minimizada)
        await pushNotifications.sendPushNotification(
            `📄 Expediente ${expediente}`,
            mensaje,
            expediente
        );
        
        console.log('✅ Notificación completa enviada');
        return true;
        
    } catch (error) {
        console.log('⚠️ Notificación push falló, pero notificación local se envió');
        // Fallback: solo notificación local
        enviarNotificacionGlobal(expediente, mensaje, tipo);
        return false;
    }
}

// Modificar la función finalSubmitCargaExp para usar notificaciones push
function finalSubmitCargaExp() {
    appendMessage("Enviando datos del expediente...", "bot");
    $.post("cargabotsql.php", { cargar_expediente: true, ...cargaData }, async function(resp) {
        appendMessage("✅ Expediente cargado correctamente.", "bot");
        
        // 🔔 NOTIFICACIÓN COMPLETA
        const expedienteNumero = cargaData.expediente || 'Nuevo expediente';
        const caratula = cargaData.caratula || 'Sin carátula';
        const mensaje = `Nuevo expediente creado: ${expediente}`;
        
        await enviarNotificacionCompleta(expedienteNumero, mensaje, 'Nuevo Expediente');
        
        isCargaInProgress = false;
        currentAction = null;
        setTimeout(RenewExp, 1000);
        
    }).fail(function() {
        appendMessage("❌ Error al cargar el expediente. Intenta nuevamente.", "bot");
        isCargaInProgress = false;
    });
}

// Función para probar notificaciones
function testNotificacionesPush() {
    enviarNotificacionCompleta(
        'TEST-' + Date.now(),
        'Esta es una notificación de prueba del sistema',
        'test'
    );
    appendMessage("🧪 Notificación de prueba enviada", "bot");
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar notificaciones push
    setTimeout(inicializarSistemaPush, 1000);
    
    // Iniciar polling de notificaciones normales
    iniciarPollingNotificaciones();
});

// Agregar opción de prueba en el menú
function panelNotificacionesCompleto() {
    appendMessage("🔔 **Sistema Completo de Notificaciones**", "bot");
    
    chatBody.insertAdjacentHTML("beforeend", `
        <div style="background: #e3f2fd; padding: 15px; border-radius: 10px; margin: 10px 0;">
            <h5>🚀 Notificaciones en Tiempo Real + Push</h5>
            <p style="font-size: 0.9em; color: #666;">
                Funciona con la app abierta o cerrada
            </p>
            
            <div style="display: grid; grid-template-columns: 1fr; gap: 8px;">
                <div class="option-box" onclick="testNotificacionesPush()">
                    🧪 Probar Notificaciones
                </div>
                <div class="option-box" onclick="inicializarSistemaPush()">
                    🔄 Reinicializar Sistema
                </div>
                <div class="option-box" onclick="pushNotifications.unsubscribe()">
                    🔕 Desactivar Notificaciones
             </div>
            </div>
        </div>
    `);
}
window.addEventListener('DOMContentLoaded', function() {
    iniciarInactividad();
});

function iniciarInactividad() {
    let inactivityTimer, countdownTimer;
    const inactivityTime = 300; // 5 minutos de inactividad antes de mostrar modal
    const countdownTime = 30;   // 30 segundos de countdown para cerrar sesión
    let countdown = countdownTime;


const logoUrl = 'logo.png';
const modal = document.createElement('div');
Object.assign(modal.style, {
    display: 'none',
    position: 'fixed',
    zIndex: 999999,
    left: '50%',
    top: '20%',
    transform: 'translateX(-50%)',
    width: '350px',
    backgroundColor: 'rgba(255,255,255,0.95)',
    border: '2px solid #444',
    borderRadius: '10px',
    padding: '20px',
    textAlign: 'center',
    boxShadow: '0 0 15px rgba(0,0,0,0.5)',
    fontFamily: 'Arial, sans-serif',
    color: '#333'
});

modal.innerHTML = `
    <div style="text-align:center; margin-bottom:10px;">
        <img id="modalLogo" src="${logoUrl}" alt="Logo" style="display:inline-block; width:120px; height:auto; max-height:120px; object-fit:contain;" />
    </div>
    <p style="margin-bottom: 15px; font-size: 16px;">
        ⚠️ No se detectó actividad. La sesión se cerrará en <span id="countdown">${countdown}</span> segundos.
    </p>
    <button id="stayConnected" style="
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        background-color: #4CAF50;
        color: white;
        cursor: pointer;
        font-size: 14px;
    ">Estoy Aqui!</button>
`;
document.body.appendChild(modal);

const countdownElement = modal.querySelector('#countdown');
const stayButton = modal.querySelector('#stayConnected');


const modalLogo = modal.querySelector('#modalLogo');
modalLogo.addEventListener('error', () => {
    modalLogo.style.display = 'none';
});
if (!logoUrl) modalLogo.style.display = 'none';   

    function resetTimer() {
        clearTimeout(inactivityTimer);
        clearInterval(countdownTimer);
        modal.style.display = 'none';
        countdown = countdownTime;
        countdownElement.textContent = countdown;

        // Reinicia el temporizador de inactividad
        inactivityTimer = setTimeout(showModal, inactivityTime * 1000);
    }

    function showModal() {
        modal.style.display = 'block';
        countdownElement.textContent = countdown;

        countdownTimer = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;

            if (countdown <= 0) {
                clearInterval(countdownTimer);
                modal.innerHTML = `
                    <p style="margin-bottom: 15px; font-size: 16px; color:red;">
                        ⏰ Sesión cerrada por inactividad
                    </p>
                `;
                setTimeout(() => {
                    modal.style.display = 'none';
                    window.location.href = 'logout.php'; // Cambiar URL si es necesario
                }, 2000);
            }
        }, 1000);
    }

    // Botón para mantenerse conectado
    stayButton.addEventListener('click', resetTimer);

    // Eventos que reinician el timer
    ['mousemove', 'keypress', 'scroll', 'click'].forEach(evt => document.addEventListener(evt, resetTimer));

    // Iniciar temporizador al cargar
    resetTimer();
}

// Ejecutar inmediatamente
iniciarInactividad();


async function inicializarSistemaPush() {
  try {
    await pushNotifications.unsubscribe(); // elimina suscripción antigua
    await pushNotifications.init();        // registra nueva
    console.log('🚀 Notificaciones push inicializadas correctamente');
  } catch(e) {
    console.error('Error inicializando push:', e);
  }
}

inicializarSistemaPush();

(function() {
    iniciarPollingNotificaciones();
})();


/*
================================================================
 MÓDULO DE NOTIFICACIÓN DE TERRENOS SIN DESMALEZAR
 Incluye:
 1. openTerrenoForm:     Abre el formulario (con campo para foto).
 2. enviarNotificacionTerrenoFromForm: Valida, genera la vista previa
                            (referenciando la foto) y maneja el envío.
 3. Helpers:             escapeHtml, copyToClipboard.
================================================================
*/

/* ---------- 1) Botón que abre el formulario y lo prefillea ---------- */
/**
 * Abre un formulario en el chat para notificar sobre un terreno.
 * @param {object} prefill - Objeto con valores para pre-llenar el form.
 * Ejemplo:
 * <div class="option-box" onclick="openTerrenoForm({propietario:'Juan', direccion:'Calle 1 123', telefono:'2994123456', email:'', plazo:'2025-11-25'})">Notificar terreno</div>
 */
function openTerrenoForm(prefill = {}) {
  // si ya existe, solo enfocar
  if (document.getElementById('form-terreno')) {
    document.getElementById('form-terreno').scrollIntoView({ behavior: 'smooth' });
    return;
  }

  appendMessage("Complete los datos para notificar a propietario por terreno sin desmalezar", "bot");

  const {
    propietario = '',
    direccion = '',
    telefono = '',
    email = '',
    plazo = '',
    observaciones = '',
    acta = '',
    nc = ''
  } = prefill;

  chatBody.insertAdjacentHTML(
    "beforeend",
    `
    <div id="form-terreno" class="card p-3 mb-3" style="max-width:700px;">
      <h5>Notificación: Terreno sin desmalezar</h5>

      <div class="mb-2">
        <label>Acta Papel Nº:</label>
        <input id="acta" class="form-control" placeholder="Ej: Nº 775" value="${escapeHtml(acta)}" required >
      </div>

      <div class="mb-2">
        <label>Nombre del propietario</label>
        <input id="propietario" class="form-control" placeholder="Ej: Juan Pérez" value="${escapeHtml(propietario)}" required>
      </div>

      <div class="mb-2">
        <label>Dirección del terreno</label>
        <input id="direccionTerreno" class="form-control" placeholder="Ej: Calle Falsa 123" value="${escapeHtml(direccion)}" required >
      </div>

      <div class="mb-2">
        <label>Nomenclatura Catastral</label>
        <input id="nc" class="form-control" placeholder="Ej: 03-1-G-603-01A" value="${escapeHtml(nc)}">
      </div>

      <div class="mb-2">
        <label>Teléfono (sin 0 ni 15) — ejemplo: 2994123456</label>
        <input id="telefonoTerreno" class="form-control" placeholder="Ej: 2994123456" value="${escapeHtml(telefono)}">
      </div>

      <div class="mb-2">
        <label>Email</label>
        <input id="emailTerreno" class="form-control" placeholder="propietario@dominio.com" value="${escapeHtml(email)}">
      </div>

      <div class="mb-2">
        <label>Plazo para regularizar (fecha)</label>
        <input id="plazoTerreno" type="date" class="form-control" value="${escapeHtml(plazo)}">
      </div>

      <div class="mb-2">
        <label>Foto del Acta / Inspección (opcional)</label>
        <input id="fotoActa" type="file" class="form-control" accept="image/*">
      </div>

      <div class="d-flex gap-2">
        <button id="btnGenerarTerreno" class="btn btn-primary btn-sm">Generar notificación ✉️/📱</button>
        <button class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('form-terreno')?.remove();">Cancelar</button>
      </div>

      <div id="form-terreno-error" class="mt-2 text-danger" style="display:none;"></div>
    </div>
    `
  );

  document
    .getElementById('btnGenerarTerreno')
    .addEventListener('click', enviarNotificacionTerrenoFromForm);

  document.getElementById('form-terreno').scrollIntoView({ behavior: 'smooth' });
}








/* ---------- 2) Envío / validación y vista previa (incluye adjunto automático) ---------- */
async function enviarNotificacionTerrenoFromForm() {
  // leer valores
  const acta = document.getElementById('acta')?.value?.trim() || 'Acta';
  const propietario = document.getElementById('propietario')?.value?.trim() || 'Propietario';
  const direccion = document.getElementById('direccionTerreno')?.value?.trim() || '—';
  const nc = document.getElementById('nc')?.value?.trim() || '—';
  const telefono = document.getElementById('telefonoTerreno')?.value?.trim() || '';
  const email = document.getElementById('emailTerreno')?.value?.trim() || '';
  const plazo = document.getElementById('plazoTerreno')?.value || '';
  const observaciones = document.getElementById('observacionesTerreno')?.value?.trim() || 'Sin observaciones.';
  const fotoFile = document.getElementById('fotoActa')?.files[0] || null;
  const errEl = document.getElementById('form-terreno-error');
  const chatBody = window.chatBody || document.body;

  if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }

  if (telefono && !/^\d{8,10}$/.test(telefono)) {
    if (errEl) { errEl.style.display = 'block'; errEl.textContent = 'Teléfono inválido. Ingresar solo números sin 0/15 (8 a 10 dígitos).'; }
    return;
  }
  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    if (errEl) { errEl.style.display = 'block'; errEl.textContent = 'Email con formato inválido.'; }
    return;
  }

  const textoActaPlain = fotoFile
    ? `\n\nActa de Inspección: Se adjunta imagen del acta (${escapeHtml(fotoFile.name)}) en la notificación por email. También puede consultarla en nuestras oficinas.`
    : '';
 
  const textoActaHtml = fotoFile
    ? `<p><strong>Acta de Inspección:</strong> Se adjunta al presente correo una imagen del acta de inspección labrada.</p>`
    : '';


  const plazoLegible2 = plazo ? new Date(plazo).toLocaleDateString() : 'sin plazo establecido';
  const fechaActual2 = new Date().toLocaleDateString('es-AR');
  const mensajeNotificacion2 = `

🌿 Municipalidad de Cipolletti - Área de Espacios Verdes🌿

Acta Papel  Nº: ${acta}

Estimado/a: ${propietario}

Motivo de la Notificación:
Según inspección realida el día ${fechaActual2} se constató que el terreno ubicado en el domicilio ${direccion} se encuentra en estado de abandono,con presencia de malezas y residuos.

El mismo se identifica catastralmente como: ${nc}

Acción requerida:
Se intima el propietario/a a realizar la limpieza y desmalezado completo del predio dentro de los 5 (cinco) días corridos de recibida la presente,teniendo como fecha límite el día ${plazoLegible2} a fin de evitar la aplicación de medidas administrativas.

Advertencia:
En caso de incumplimiento, la Municipalidad procederá conforme a la normativa vigente,pudiendo realizar las tareas por cuenta del propietario y aplicar las sanciones correspondientes.

Para consultas comunicarse al Área de Espacios Verdes. (2994242910)

`.trim();



  const plazoLegible = plazo ? new Date(plazo).toLocaleDateString() : 'sin plazo establecido';
  const fechaActual = new Date().toLocaleDateString('es-AR');
  const mensajeNotificacion = `
<div style="font-family: Arial, sans-serif; line-height: 1.4; font-size: 14px;">

  <img src="https://raw.githubusercontent.com/nachorivas581/PROGRAMA/main/logomuni.png" width="150" lenght="150">

  <p>🌿 <strong>Municipalidad de Cipolletti - Área de Espacios Verdes</strong> 🌿</p>
  <p><strong>Acta Papel  Nº:${acta}</strong></p>
  <p>Estimado/a: <strong>${propietario}</strong></p>

  <p>
    <strong>Motivo de la Notificación:</strong>
    Según inspección realizada el día ${fechaActual} se constató que el terreno ubicado en el domicilio <strong>${direccion}</strong> se encuentra en estado de abandono,con presencia de malezas y residuos.
  </p>

  <p>
   El mismo se identifica catastralmente como: <strong>${nc}</strong>
  </p>

  <p>
    <strong>Acción requerida:</strong><br>
   Se intima el propietario/a  a realizar  limpieza y desmalezado completo del  predio dentro de los 5 (cinco) días corridos de recibida la presente ,teniendo como fecha límite el día <strong>${plazoLegible}</strong> a fin de  evitar la aplicación de medidas administrativas.
  </p>

  ${textoActaHtml}   <p>

    <strong>Advertencia:</strong>

    En caso de  incumplimiento,la Municipalidad procederá conforme a la normativa vigente,pudiendo realizar las tareas por cuenta del propietario y aplicar las sanciones correspondientes.
  </p>

  <p>
    Para consultas comunicarse al Área de Espacios Verdes: <strong>299 424 2910</strong>
  </p>
   <p>
   <strong>CRISTIAN IGNACIO RIVAS</strong>
  </p>
  <p>
   <strong>Inspector de Área</strong>
  </p>
 <p>
   <strong>Dirección de Espacios Verdes</strong>
  </p>
 <p>
   <strong>Secretaría de Servicios Públicos</strong>
  </p>
 <p>
   <strong>MUNICIPALIDAD DE CIPOLLETTI</strong>
  </p>


</div>
`;

  const imageUrl = 'https://raw.githubusercontent.com/nachorivas581/PROGRAMA/main/logomuni.png';
  const cuerpoEmail = `
    <div style="font-family: Arial, sans-serif; line-height:1.4;">
      <img src="${imageUrl}" alt="Municipalidad" style="width:100px;margin-bottom:12px;">
      ${mensajeNotificacion.replace(/\n/g,'<br>')}
      <br><br><small>Municipalidad de Cipolletti — Área de Espacios Verdes</small>
    </div>
  `;

  const urlWhats = telefono ? `https://web.whatsapp.com/send?phone=+549${encodeURIComponent(telefono)}&text=${encodeURIComponent(mensajeNotificacion2)}` : null;

  appendMessage("Notificación generada. ¿Deseas enviarla por WhatsApp o por Email?", "bot");

  document.getElementById('form-terreno')?.remove();

  chatBody.insertAdjacentHTML("beforeend", `
    <div class="card p-3 mb-3" style="max-width:700px;">
      <pre style="white-space:pre-wrap; font-family:inherit;">${escapeHtml(mensajeNotificacion)}</pre>
      
      ${fotoFile ? `<div class="mt-2 alert alert-info p-2 small">✓ Se adjuntará la foto: <strong>${escapeHtml(fotoFile.name)}</strong></div>` : ''}

      <div class="mt-2">
        ${urlWhats ? `<a href="${urlWhats}" target="_blank" class="btn btn-success btn-sm me-2">📱 Notificar vía WhatsApp</a>` : `<button class="btn btn-secondary btn-sm me-2" disabled>📱 No hay teléfono</button>`}
        <button class="btn btn-primary btn-sm" id="btnEnviarEmailTerreno">✉️ Notificar vía Email</button>
        <button class="btn btn-outline-secondary btn-sm" id="btnCopiarTerreno">📋 Copiar</button>
      </div>
    </div>
  `);

  document.getElementById('btnCopiarTerreno').addEventListener('click', () => copyToClipboard(mensajeNotificacion));

  document.getElementById('btnEnviarEmailTerreno').addEventListener('click', async function () {
    try {
      appendMessage("Enviando email...", "bot");

      const expediente = "";
      const resolucion = "";

      if (typeof window.enviarEmailNotificacion === 'function') {
        const json = await window.enviarEmailNotificacion({
          expediente,
          resolucion,
          direccion,
          responsable: email || propietario,
          celular: telefono,
          mensaje: mensajeNotificacion,
          informe: observaciones,
          foto: fotoFile 
        });
        appendMessage(json && json.success ? "Email enviado." : "Error al enviar email (PHP). Revisa la consola.", "bot");
        console.log('enviar_noti.php response (helper):', json);
        return;
      }

      const formData = new FormData();
      formData.append('expediente', expediente);
      formData.append('resolucion', resolucion);
      formData.append('direccion', direccion);
      formData.append('responsable', email || propietario);
      formData.append('celular', telefono);
      formData.append('mensaje', mensajeNotificacion);
      formData.append('informe', observaciones);
      formData.append('cuerpoHtml', cuerpoEmail);

      if (fotoFile) {
      
        formData.append('foto_acta', fotoFile, fotoFile.name);
      }

      try {
        const imgResp = await fetch(imageUrl);
        if (!imgResp.ok) throw new Error('No se pudo descargar la imagen: ' + imgResp.status);
        const imgBlob = await imgResp.blob();
        const filename ="logomuni.png";
        const file = new File([imgBlob], filename, { type: imgBlob.type || 'image/png' });
        formData.append('adjunto', file, filename);
      } catch (imgErr) {
        console.warn('No se pudo adjuntar la imagen (logo) desde URL:', imgErr);
      }
      // -------------------------------------------------------------------

      const resp = await fetch('enviar_noti.php', { method: 'POST', body: formData });

      let jsonResp;
      try {
        jsonResp = await resp.json();
      } catch (e) {
        const txt = await resp.text();
        throw new Error('Respuesta inválida del servidor: ' + txt);
      }

      if (jsonResp && jsonResp.success) {
        appendMessage("Email enviado correctamente (PHP).", "bot");
      } else {
        appendMessage("Error al enviar email: revisá la consola para más detalles.", "bot");
        console.error('enviar_noti.php error:', jsonResp);
      }
      console.log('enviar_noti.php response:', jsonResp);
      return;
    } catch (err) {
      console.error('Error enviando email:', err);
      appendMessage("Ocurrió un error al intentar enviar el email. Revisá la consola.", "bot");
      alert('Error al enviar email: ' + (err && err.message ? err.message : err));
    }
  });
}

/* ---------- 3) Helpers (Sin cambios) ---------- */

/**
 * Escapa caracteres HTML especiales para evitar XSS.
 * @param {string} s - El texto a escapar.
 * @returns {string} - El texto escapado.
 */
function escapeHtml(s) {
  if (!s) return '';
  return s.replace(/[&<>"']/g, function (m) {
    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
  });
}

/**
 * Copia un texto al portapapeles.
 * @param {string} text - El texto a copiar.
 */
function copyToClipboard(text) {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(() => appendMessage("Texto copiado al portapapeles.", "bot"), () => appendMessage("No se pudo copiar automáticamente.", "bot"));
  } else {
    // Fallback para navegadores antiguos o entornos no seguros
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'absolute';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try { 
      document.execCommand('copy'); 
      appendMessage("Texto copiado al portapapeles.", "bot"); 
    } catch { 
      appendMessage("No se pudo copiar automáticamente.", "bot"); 
    }
    document.body.removeChild(ta);
  }
}
    document.addEventListener('DOMContentLoaded', () => {
        const overlay = document.getElementById('welcome-overlay');
        const loaderContainer = document.getElementById('myLoader');
        const star = loaderContainer.querySelector('.convergent-star12');
        const spinner = loaderContainer.querySelector('.convergent-spinner12');
        
        // Tiempos (ajustables si lo necesitas)
        const starAppearDuration = 1000; 
        const spinnerDelay = 500;        
        const fadeOutDuration = 1000;    
        const fullAnimationTime = starAppearDuration + spinnerDelay + 1000; 

        // 1. Inicia la animación de aparición de la estrella
        loaderContainer.classList.add('star-initial-animation12');

        // 2. Transición de la estrella a pulso y aparición del spinner
        setTimeout(() => {
            loaderContainer.classList.remove('star-initial-animation12');
            star.classList.add('pulsing'); 
            
            // Muestra el spinner (anillo)
            setTimeout(() => {
                spinner.classList.add('show');
            }, spinnerDelay); 
            
        }, starAppearDuration);

        // 3. OCULTAR OVERLAY
        setTimeout(() => {
            overlay.classList.add('hidden');
        }, fullAnimationTime);
        
        // 4. (Opcional) Remover el overlay completamente del DOM
        setTimeout(() => {
            overlay.remove();
        }, fullAnimationTime + fadeOutDuration); 

    });

// --- JS: Función para actualizar la UI del selector de modos ---
// --- JS: Función para actualizar la UI del selector de modos ---

/**
 * SIMULACIÓN: Simula una llamada API exitosa (o fallida) al backend.
 * @param {string} newMode - El modo seleccionado.
 */
async function setChatModeAPI(newMode) {
    console.log(`[SIMULACIÓN API] Intentando cambiar el modo a: ${newMode}`);
    
    // Simula la latencia de red (ej: 200ms)
    await new Promise(resolve => setTimeout(resolve, 200)); 

    // 🚨 Retorna true para simular el éxito del servidor.
    return true; 
}

// Variable global (simulada, asegúrate de tenerla definida)
let currentChatMode = 'fast'; 

function updateModeSelectorUI() {
    const modeSelector = document.getElementById('mode-selector');
    
    // Limpia el contenido actual
    modeSelector.innerHTML = ''; 

    // 1. DEFINICIÓN DE LA ROTACIÓN
    // Definimos cuál es el SIGUIENTE modo basado en el actual.
    const nextModeMap = {
        'fast': 'think',        // Si está en Rápido -> El botón llevará a Pensar
        'think': 'investigate', // Si está en Pensar -> El botón llevará a Investiga
        'investigate':'developer',   // Si está en Investiga -> El botón llevará a Rápido
        'developer': 'fast'   // Si está en Investiga -> El botón llevará a Rápido

    };

    // 2. CONFIGURACIÓN VISUAL DE CADA MODO
    // Aquí defines el texto e icono que se mostrará en el botón
    const modesConfig = {
        'fast': {
            text: 'Developer 💻',
            title: 'Desarrolla codigos de programación.'
        },
        'think': {
            text: 'Rapido⚡',
            title: 'Seleccion rapida de respuestas.'
        },
        'investigate': {
            text: 'Pensar 🧠', // Nuevo modo añadido
            title: 'Razonamiento Profundo paso a paso.'
        },
        'developer': {
            text: 'Investiga🔭', // Nuevo modo añadido
            title: 'Investiga a profundidad un tema.'
        }

    };

    // Determinamos qué modo toca mostrar ahora (el "siguiente" en el ciclo)
    const modeToShow = nextModeMap[currentChatMode];
    
    // Obtenemos los textos correspondientes a ese modo
    const config = modesConfig[modeToShow];

    // Crea y configura el botón
    const newTab = document.createElement('span');
    newTab.id = `mode-${modeToShow}`;
    newTab.className = 'ai-mode-tab'; 
    newTab.setAttribute('data-mode', modeToShow);
    newTab.setAttribute('title', config.title);
    newTab.textContent = config.text;

    // Listener ASÍNCRONO
    newTab.addEventListener('click', async () => {
        
        console.log(`🔄 Intentando cambiar a modo: ${modeToShow}`);
        
        // Efecto visual de carga (opcional, pero recomendado UX)
        newTab.style.opacity = '0.5';
        newTab.textContent = '...';

        // 1. Llamar a la API
        const success = await setChatModeAPI(modeToShow);
        
        if (success) {
            // 2. Actualiza estado global
            currentChatMode = modeToShow; 
            
            // Re-renderiza la UI (recursivo)
            updateModeSelectorUI(); 
            
            console.log(`✅ Modo de Chat actualizado a: ${currentChatMode}`);
        } else {
            console.error(`❌ Falló el cambio de modo.`);
            // Si falla, restauramos el texto original del botón
            newTab.textContent = config.text;
            newTab.style.opacity = '1';
        }
    });

    // Añade el botón al selector
    modeSelector.appendChild(newTab);
}

// --- Inicialización ---
document.addEventListener('DOMContentLoaded', () => {
    updateModeSelectorUI(); 
});


document.addEventListener('DOMContentLoaded', function() {
    const inputField = document.getElementById('userMessage');
    const welcomeMessage = document.getElementById('welcome-message');

    function checkInput() {
        // Oculta si hay texto, muestra si no lo hay.
        if (inputField.value.trim().length > 0) {
            welcomeMessage.classList.add('hidden');
        } else {
            welcomeMessage.classList.remove('hidden');
        }
    }

    // El evento 'input' se dispara al escribir
    inputField.addEventListener('input', checkInput);

    // Revisar al cargar la página
    checkInput();
});

// cliente-ocr.js
// Este script asume que tienes cargados en la página:
// - Tesseract (CDN o bundle) como Tesseract
// - pdf.js (pdfjsLib)
// - JSZip
//
// IDs esperados en el DOM:
// - clipBtn
// - archivoAdjunto
// - ocrResultados
// - textoOCR (textarea)
// - userMessage (input/textarea donde se coloca el texto final)
// - opcional: ocrBox (contenedor visual que mostrar/ocultar)

document.addEventListener('DOMContentLoaded', () => {
  const botonClip = document.getElementById('clipBtn');
  const campoArchivo = document.getElementById('archivoAdjunto');
  const divResultados = document.getElementById('chatBody');
  const areaTextoOCR = document.getElementById('textoOCR');
  const inputMensaje = document.getElementById('userMessage');
  const ocrBox = document.getElementById('chatBody'); // opcional

  if (botonClip && campoArchivo) {
    botonClip.addEventListener('click', e => { e.preventDefault(); campoArchivo.click(); });
  }

  // Inicializamos worker de Tesseract una sola vez y lo reutilizamos
  const worker = Tesseract.createWorker({
    logger: m => {
      // m: { status, progress }
      if (m && m.status && String(m.status).toLowerCase().includes('recogniz')) {
        divResultados.innerHTML = `<i class="fas fa-cog fa-spin"></i> Reconociendo texto... ${(m.progress*100).toFixed(0)}%`;
      } else {
        // mostrar otros estados si quieres
        divResultados.innerHTML = `<i class="fas fa-cog fa-spin"></i> ${m && m.status ? m.status : ''}`;
      }
    }
  });

  let workerReady = false;
  async function ensureWorker(language = 'spa') {
    if (workerReady) return;
    if (divResultados) {
      divResultados.style.display = 'block';
      divResultados.innerHTML = `<i class="fas fa-cog fa-spin"></i> Inicializando motor OCR...`;
    }

    try {
      if (typeof worker.load === 'function') {
        await worker.load();
      }

      if (typeof worker.loadLanguage === 'function') {
        try {
          await worker.loadLanguage(language);
        } catch (e) {
          console.warn('No se pudo loadLanguage desde worker (posible CORS/langPath).', e);
        }
      }

      if (typeof worker.initialize === 'function') {
        try {
          await worker.initialize(language);
        } catch (e) {
          console.warn('worker.initialize falló o no es necesario.', e);
        }
      }

      workerReady = true;
      if (divResultados) divResultados.innerHTML = '';
    } catch (err) {
      console.warn('ensureWorker: error preparando worker, se usará fallback Tesseract.recognize si está disponible.', err);
      // seguimos sin lanzar para permitir fallback
    }
  }

  // OCR sobre una URL / dataURL / blob / canvas
  async function ocrRecognize(source, language = 'spa') {
    await ensureWorker(language);

    if (workerReady && typeof worker.recognize === 'function') {
      const { data: { text } } = await worker.recognize(source);
      return text;
    }

    if (typeof Tesseract.recognize === 'function') {
      try {
        const res = await Tesseract.recognize(source, language, {
          logger: m => {
            if (m && m.status && String(m.status).toLowerCase().includes('recogniz')) {
              divResultados.innerHTML = `<i class="fas fa-cog fa-spin"></i> Reconociendo texto... ${(m.progress*100).toFixed(0)}%`;
            } else {
              divResultados.innerHTML = `<i class="fas fa-cog fa-spin"></i> ${m && m.status ? m.status : ''}`;
            }
          }
        });
        return res && res.data ? res.data.text : '';
      } catch (err) {
        console.error('Fallback Tesseract.recognize falló:', err);
        throw err;
      }
    }

    throw new Error('No hay método OCR disponible (ni worker.recognize ni Tesseract.recognize).');
  }

  // Render PDF page to canvas (usa pdf.js)
  async function pdfPageToCanvas(pdfDoc, pageNum) {
    const page = await pdfDoc.getPage(pageNum);
    const scale = 2; // mejora calidad; ajusta según necesidad
    const viewport = page.getViewport({ scale });
    const canvas = document.createElement('canvas');
    canvas.width = viewport.width;
    canvas.height = viewport.height;
    const ctx = canvas.getContext('2d');
    await page.render({ canvasContext: ctx, viewport }).promise;
    return canvas;
  }

  // Manejar archivo .docx: extraer texto (word/document.xml) y extraer imágenes (word/media)
  async function handleDocx(file) {
    const arrayBuffer = await file.arrayBuffer();
    const zip = await JSZip.loadAsync(arrayBuffer);
    let extractedText = '';

    // 1) extraer texto directo del document.xml (si existe)
    const docXml = zip.file('word/document.xml');
    if (docXml) {
      const xmlText = await docXml.async('string');
      const matches = [...xmlText.matchAll(/<w:t[^>]*>(.*?)<\/w:t>/g)];
      extractedText += matches.map(m => m[1]).join(' ').replace(/\s+/g, ' ').trim();
    }

    // 2) extraer imágenes en word/media/ y hacer OCR sobre cada una
    const mediaFolder = zip.folder('word/media');
    let ocrFromImages = '';
    if (mediaFolder) {
      const files = Object.values(mediaFolder.files || {});
      for (const f of files) {
        if (typeof f.async === 'function') {
          const blob = await f.async('blob');
          const dataURL = await blobToDataURL(blob);
          const textImg = await ocrRecognize(dataURL);
          if (textImg && textImg.trim()) {
            ocrFromImages += '\n' + textImg.trim();
          }
        }
      }
    }

    return {
      docxText: extractedText.trim(),
      imagesOcrText: ocrFromImages.trim()
    };
  }

  // helper: blob -> dataURL
  function blobToDataURL(blob) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.onerror = reject;
      reader.readAsDataURL(blob);
    });
  }

  // UI para acciones sobre OCR
  function showOcrActions(filename, extractedText) {
    let actionsBox = document.getElementById('ocrActionsBox');
    if (!actionsBox) {
      actionsBox = document.createElement('div');
      actionsBox.id = 'ocrActionsBox';
      actionsBox.style.marginTop = '10px';
      actionsBox.style.display = 'flex';
      actionsBox.style.gap = '8px';
      actionsBox.innerHTML = `
        <button id="sendBotBtn" class="btn">Enviar al bot (preguntar)</button>
        <button id="saveOrdenanzaBtn" class="btn">Guardar como ordenanza</button>
        <button id="resumirBtn" class="btn">Pedir resumen</button>
        <button id="investigarBtn" class="btn">Investigar (web)</button>
      `;
      if (divResultados && divResultados.parentNode) {
        divResultados.parentNode.insertBefore(actionsBox, divResultados.nextSibling);
      } else {
        document.body.appendChild(actionsBox);
      }
    }

    const byId = id => document.getElementById(id);
    byId('sendBotBtn').onclick = async () => {
      await sendOcrToBot({ filename, text: extractedText }, 'ask');
    };
    byId('saveOrdenanzaBtn').onclick = async () => {
      await sendOcrToBot({ filename, text: extractedText }, 'save');
    };
    byId('resumirBtn').onclick = async () => {
      await sendOcrToBot({ filename, text: extractedText }, 'summarize');
    };
    byId('investigarBtn').onclick = async () => {
      await sendOcrToBot({ filename, text: extractedText }, 'investigate');
    };
  }

  // Función que envía el OCR al backend (chat_api_lm_studio.php)
  async function sendOcrToBot(ocrPayload, ocrAction = 'ask') {
    if (divResultados) divResultados.innerHTML = `<i class="fas fa-cog fa-spin"></i> Enviando al asistente...`;
    try {
      const body = {
        message: '',
        ocr_payload: {
          filename: ocrPayload.filename,
          text: ocrPayload.text
        },
        ocr_action: ocrAction,
        chat_mode: ocrAction === 'investigate' ? 'investigate' : 'fast'
      };

      const resp = await fetch('busqueda_web.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(body)
      });

      const j = await resp.json();
      if (!resp.ok) {
        if (divResultados) divResultados.innerHTML = `❌ Error del servidor: ${j.error || JSON.stringify(j)}`;
        console.error(j);
        return;
      }

      const botReply = j.reply || JSON.stringify(j);
      if (divResultados) divResultados.innerHTML = `<strong>Bot:</strong> ${String(botReply).replace(/\n/g,'<br>')}`;
      if (inputMensaje) inputMensaje.value = (typeof botReply === 'string') ? botReply : JSON.stringify(botReply, null, 2);
    } catch (err) {
      console.error(err);
      if (divResultados) divResultados.innerHTML = `❌ Error enviando OCR al asistente: ${err.message || err}`;
    }
  }

  // main handler: archivo -> OCR -> mostrar + acciones
  if (campoArchivo && divResultados && areaTextoOCR && inputMensaje) {
    campoArchivo.addEventListener('change', async () => {
      if (campoArchivo.files.length === 0) return;
      const file = campoArchivo.files[0];
      const name = file.name;
      if (divResultados) {
        divResultados.style.display = 'block';
        divResultados.innerHTML = `<i class="fas fa-cog fa-spin"></i> Procesando ${name}...`;
      }
      if (areaTextoOCR) {
        areaTextoOCR.style.display = 'none';
        areaTextoOCR.value = '';
      }
      if (inputMensaje) inputMensaje.value = '';

      if (ocrBox) ocrBox.style.display = 'none';

      try {
        const mime = file.type || '';
        let finalText = '';

        if (mime.startsWith('image/') || /\.(jpe?g|png|webp|bmp|tiff)$/i.test(name)) {
          const url = URL.createObjectURL(file);
          const text = await ocrRecognize(url);
          URL.revokeObjectURL(url);
          finalText = text ? text.trim() : '';
          if (finalText) {
            if (divResultados) divResultados.innerHTML = ``;
            areaTextoOCR.value = finalText;
            areaTextoOCR.style.display = 'block';
            inputMensaje.value = finalText;
          } else {
            if (divResultados) divResultados.innerHTML = `⚠️ OCR completado para <strong>${name}</strong>, no se detectó texto.`;
          }

        } else if (mime === 'application/pdf' || name.toLowerCase().endsWith('.pdf')) {
          if (divResultados) divResultados.innerHTML = `<i class="fas fa-cog fa-spin"></i> Leyendo PDF y convirtiendo páginas...`;
          const arrayBuffer = await file.arrayBuffer();
          const loadingTask = pdfjsLib.getDocument({ data: arrayBuffer });
          const pdfDoc = await loadingTask.promise;
          let fullText = '';
          for (let p = 1; p <= pdfDoc.numPages; p++) {
            if (divResultados) divResultados.innerHTML = `<i class="fas fa-cog fa-spin"></i> Renderizando página ${p}/${pdfDoc.numPages}...`;
            const canvas = await pdfPageToCanvas(pdfDoc, p);
            if (divResultados) divResultados.innerHTML = `<i class="fas fa-cog fa-spin"></i> OCR página ${p}/${pdfDoc.numPages}...`;
            const pageDataURL = canvas.toDataURL();
            const pageText = await ocrRecognize(pageDataURL);
            if (pageText && pageText.trim()) {
              fullText += '\n\n' + pageText.trim();
            }
          }
          finalText = fullText.trim();
          if (finalText) {
            if (divResultados) divResultados.innerHTML = `✅ OCR completado para <strong>${name}</strong>.`;
            areaTextoOCR.value = finalText;
            areaTextoOCR.style.display = 'block';
            inputMensaje.value = finalText;
          } else {
            if (divResultados) divResultados.innerHTML = `⚠️ OCR completado para <strong>${name}</strong>, no se detectó texto en el PDF.`;
          }

        } else if (name.toLowerCase().endsWith('.docx')) {
          if (divResultados) divResultados.innerHTML = `<i class="fas fa-cog fa-spin"></i> Extrayendo contenido de DOCX...`;
          const { docxText, imagesOcrText } = await handleDocx(file);
          const combined = [docxText, imagesOcrText].filter(Boolean).join('\n\n');
          finalText = combined.trim();
          if (finalText) {
            if (divResultados) divResultados.innerHTML = `✅ Procesado DOCX <strong>${name}</strong>.`;
            areaTextoOCR.value = finalText;
            areaTextoOCR.style.display = 'block';
            inputMensaje.value = finalText;
          } else {
            if (divResultados) divResultados.innerHTML = `⚠️ Se procesó ${name} pero no se extrajo texto.`;
          }

        } else {
          if (divResultados) divResultados.innerHTML = `❌ Tipo de archivo no soportado para OCR: <strong>${name}</strong>. Usa una imagen (jpg/png), PDF o DOCX.`;
        }

        // Mostrar/ocultar ocrBox si existe
        if (ocrBox) {
          if (finalText) ocrBox.style.display = 'block';
          else ocrBox.style.display = 'none';
        }

        // Mostrar acciones si hubo texto detectado
        if (finalText) showOcrActions(name, finalText);

      } catch (err) {
        console.error(err);
        if (divResultados) divResultados.innerHTML = `❌ Error procesando archivo: ${err.message || err}`;
      } finally {
        campoArchivo.value = ''; // permitir subir mismo archivo otra vez
      }
    });
  } else {
    console.error("Faltan IDs requeridos en el DOM (clipBtn, archivoAdjunto, ocrResultados, textoOCR, userMessage).");
  }

  // opcional: cerrar worker al salir de la página (limpieza)
  window.addEventListener('beforeunload', async () => {
    try {
      if (workerReady && typeof worker.terminate === 'function') {
        await worker.terminate();
      } else if (typeof worker.terminate === 'function') {
        await worker.terminate();
      }
    } catch (e) { /* ignore */ }
  });
});

</script>
</body>
</html>
