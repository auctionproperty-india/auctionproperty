<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

include 'header.php';
?>

<div class="container py-5">
    <div class="card shadow-lg border-0 rounded-4 p-5 text-center mx-auto" style="max-width: 600px;">
        <i class="fab fa-whatsapp text-success mb-3" style="font-size: 4rem;"></i>
        <h3 class="fw-bold mb-3">Connect WhatsApp Business App</h3>
        <p class="text-muted mb-4">Apne mobile ke WhatsApp Business app ko is CRM se jodein. Aapka number, chat history aur app sab safe rahega.</p>
        
        <!-- Facebook SDK -->
        <script>
            window.fbAsyncInit = function() {
                FB.init({
                    appId      : '1832057331478345', // ✅ Aapka App ID
                    autoLogAppEvents : true,
                    xfbml      : true,
                    version    : 'v23.0'
                });
            };
        </script>
        <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>

        <button id="wa-connect-btn" class="btn btn-success btn-lg rounded-pill px-5 py-3 fw-bold">
            <i class="fab fa-whatsapp me-2"></i> Connect WhatsApp (Scan QR)
        </button>
    </div>
</div>

<script>
    // 1. Meta se WABA ID aur Phone ID receive karna
    window.addEventListener('message', (event) => {
        if (event.origin !== "https://www.facebook.com" && event.origin !== "https://web.facebook.com") return;
        let data;
        try { data = JSON.parse(event.data); } catch (e) { return; }
        
        if (data.type === 'WA_EMBEDDED_SIGNUP') {
            if (data.event === 'FINISH' || data.event === 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING') {
                const { phone_number_id, waba_id } = data.data;
                saveWhatsAppConnection(phone_number_id, waba_id);
            } else if (data.event === 'CANCEL') {
                alert('Connection cancelled.');
            }
        }
    });

    // 2. Button dabane par Facebook Popup kholna
    document.getElementById('wa-connect-btn').addEventListener('click', () => {
        FB.login(function(response) {
            if (response.authResponse) {
                const code = response.authResponse.code;
                if (code) {
                     window.fbAuthCode = code;
                }
            }
        }, {
            config_id: '1140373918569755', // ✅ Aapki Config ID
            response_type: 'code',
            override_default_response_type: true,
            extras: {
                setup: {} // 🔥 Yahan change kiya gaya hai. Isse popup band nahi hoga aur QR aayega.
            }
        });
    });

    // 3. Data ko backend (PHP) me bhejna
    function saveWhatsAppConnection(phoneId, wabaId) {
        if (!window.fbAuthCode) {
            alert('Error: Auth code missing. Please try again.');
            return;
        }
        
        const formData = new FormData();
        formData.append('code', window.fbAuthCode);
        formData.append('phone_number_id', phoneId);
        formData.append('waba_id', wabaId);

        fetch('whatsapp_save_connection.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('✅ WhatsApp Connected Successfully!');
                window.location.href = 'sales_dashboard.php';
            } else {
                alert('❌ Error: ' + data.message);
            }
        });
    }
</script>

<?php include 'footer.php'; ?>
