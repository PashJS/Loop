<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../backend/config.php';

// Fetch user balance
$balance = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT xpoints FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch();
        if ($row) {
            $balance = (int)($row['xpoints'] ?? 0);
        }
    } catch (PDOException $e) {
        // Handle case where column might not exist
        $balance = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        // Apply theme instantly before page renders
        (function() {
            const saved = localStorage.getItem('floxwatch_theme');
            const theme = saved === 'light' || saved === 'dark' ? saved : 
                          (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
            if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XPoints - Loop</title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="home.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="layout.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="xpoints.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* Base styles for space theme transparency */
        body {
            background: transparent !important;
            margin: 0; padding: 0;
            font-family: 'Outfit', sans-serif;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        html { background: #000; }

        .app-layout { 
            position: relative;
            z-index: 1;
            background: transparent !important; 
            height: auto !important; 
        }

        .side-nav {
            background: rgba(0,0,0,0.1) !important;
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
        }

        .top-nav {
            background: rgba(0,0,0,0.4) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.05) !important;
        }

        .main-content { 
            background: transparent !important; 
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }

        #starfield {
            position: fixed; 
            top: 0; 
            left: 0;
            width: 100%; 
            height: 100%;
            z-index: 0; 
            pointer-events: none;
            background: #000;
        }
    </style>

    <!-- Added buyer-country=US to force Hosted Fields eligibility in Sandbox -->
    <script src="https://www.paypal.com/sdk/js?client-id=sb&components=buttons,hosted-fields&currency=USD&buyer-country=US"></script>
</head>
<body>
    <canvas id="starfield"></canvas>
    <?php include('header.php'); ?>
    
    <div class="app-layout">
        <?php include('sidebar.php'); ?>
        
        <main class="main-content">
            <div class="xpoints-page-container">
                
                <header class="xpoints-header">
                    <h1 class="buy-xpoints-title">Buy XPoints</h1>
                    <div class="sandbox-badge">SANDBOX MODE</div>
                    <p class="text-secondary">Level up your experience with Loop currency</p>
                </header>

                <!-- Balance Card -->
                <div class="balance-card">
                    <span class="balance-label">Your Balance</span>
                    <div class="balance-amount">
                        <span id="userBalanceDisplay"><?php echo number_format($balance); ?></span>
                        <span class="xpoints-icon"><?php include('xpointsicon.html'); ?></span>
                    </div>
                </div>

                <!-- Bundles Grid -->
                <div class="bundles-grid">
                    <!-- Test Bundle -->
                    <div class="bundle-card test-bundle">
                        <div class="bundle-icon" style="background: rgba(var(--accent-rgb), 0.1);">
                            <i class="fa-solid fa-vial" style="color: var(--accent-color); font-size: 24px;"></i>
                        </div>
                        <div class="bundle-xp">1 XPoint</div>
                        <div class="bundle-price" data-amount="0.01" data-xp="1">$0.01</div>
                        <div id="paypal-button-test" class="paypal-button-container"></div>
                        <div class="test-label" style="font-size: 10px; color: var(--text-secondary); margin-top: 8px;">TEST MODE</div>
                    </div>

                    <!-- Bundle 1 -->
                    <div class="bundle-card">
                        <div class="bundle-icon">
                            <?php include('xpointsicon.html'); ?>
                        </div>
                        <div class="bundle-xp">100 XPoints</div>
                        <div class="bundle-price" data-amount="1.00" data-xp="100">$1.00</div>
                        <div id="paypal-button-100" class="paypal-button-container"></div>
                    </div>

                    <!-- Bundle 2 -->
                    <div class="bundle-card">
                        <div class="bundle-icon">
                            <?php include('xpointsicon.html'); ?>
                        </div>
                        <div class="bundle-xp">500 XPoints</div>
                        <div class="bundle-price" data-amount="4.50" data-xp="500">$4.50</div>
                        <div id="paypal-button-500" class="paypal-button-container"></div>
                    </div>

                    <!-- Bundle 3 -->
                    <div class="bundle-card">
                        <div class="bundle-icon">
                            <?php include('xpointsicon.html'); ?>
                        </div>
                        <div class="bundle-xp">1k XPoints</div>
                        <div class="bundle-price" data-amount="8.00" data-xp="1000">$8.00</div>
                        <div id="paypal-button-1000" class="paypal-button-container"></div>
                    </div>

                    <!-- Bundle 4 -->
                    <div class="bundle-card">
                        <div class="bundle-icon">
                            <?php include('xpointsicon.html'); ?>
                        </div>
                        <div class="bundle-xp">5k XPoints</div>
                        <div class="bundle-price" data-amount="35.00" data-xp="5000">$35.00</div>
                        <div id="paypal-button-5000" class="paypal-button-container"></div>
                    </div>

                    <!-- Bundle 5 -->
                    <div class="bundle-card">
                        <div class="bundle-icon" style="background: rgba(255, 215, 0, 0.1);">
                            <div style="color: #ffd700;"><?php include('xpointsicon.html'); ?></div>
                        </div>
                        <div class="bundle-xp">10k XPoints</div>
                        <div class="bundle-price" data-amount="60.00" data-xp="10000">$60.00</div>
                        <div id="paypal-button-10000" class="paypal-button-container"></div>
                    </div>
                </div>

                <!-- Calculator Section -->
                <div class="calculator-section">
                    <h2 class="calc-title">Custom Amount</h2>
                    <p class="calc-note">Enter the amount of XPoints you want and hit Enter to buy</p>
                    <div class="calc-input-wrapper">
                        <input type="number" id="xpointsInput" class="calc-input" placeholder="Enter XPoints amount..." min="1">
                    </div>
                    <div id="calcResult" class="calc-result">
                        Cost: $<span id="costValue">0.00</span>
                    </div>
                    <p class="calc-note">Standard rate: 100 XPoints = $1.00</p>
                    
                    <!-- Custom Amount Purchase Button -->
                    <div id="customBuySection" class="custom-buy-section" style="display: none;">
                        <button id="customBuyBtn" class="custom-buy-btn">
                            <i class="fa-solid fa-bolt"></i>
                            Buy <span id="customXpAmount">0</span> XPoints for $<span id="customCostAmount">0.00</span>
                        </button>
                        <div id="paypal-button-custom" class="paypal-button-container" style="margin-top: 12px;"></div>
                    </div>
                </div>


            </div>
        </main>
    </div>

    <!-- Credit/Debit Card Modal -->
    <div class="card-modal-overlay" id="cardModalOverlay">
        <div class="card-modal">
            <button class="modal-close" id="modalClose"><i class="fa-solid fa-xmark"></i></button>
            
            <!-- Left Side: Order Info -->
            <div class="modal-side-info">
                <div style="margin-bottom: 25px;">
                    <?php include('xpointsicon.html'); ?>
                </div>
                <h2 class="modal-title" style="text-align: center;">Payment</h2>
                <p class="modal-subtitle" style="text-align: center;">You're adding <strong id="modalXPCount" style="color:var(--accent-color); font-weight:800;">0</strong> XP to your balance. Your transaction is securely encrypted and handled by PayPal.</p>
                
                <div style="margin-top: auto; padding-top: 30px; border-top: 1px solid rgba(255,255,255,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: rgba(255,255,255,0.3); font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Amount Due</span>
                        <span style="color: white; font-weight: 900; font-size: 28px; letter-spacing: -1px;">$<span id="modalPayAmount">0.00</span></span>
                    </div>
                </div>
            </div>
            
            <!-- Right Side: Payment Form -->
            <div class="modal-side-form">
                <div id="payment-form" class="payment-form">
                    <div class="form-group full-row">
                        <label>Card Number</label>
                        <div id="card-number" class="input-container"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Expires</label>
                        <div id="expiration-date" class="input-container"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>CVC</label>
                        <div id="cvv" class="input-container"></div>
                    </div>

                    <div class="form-group full-row">
                        <label>Cardholder Name</label>
                        <div class="input-container">
                            <input type="text" id="card-holder-name" class="custom-text-input" placeholder="Full name on card">
                        </div>
                    </div>

                    <div class="form-group full-row">
                        <button id="submit-button" class="submit-pay-btn">Complete Payment • $<span id="modalPayAmountBtn">0.00</span></button>
                    </div>
                </div>

                <div style="margin-top: 25px; display: flex; justify-content: center; gap: 20px; opacity: 0.3;">
                    <i class="fa-brands fa-cc-visa" style="font-size: 24px; color: white;"></i>
                    <i class="fa-brands fa-cc-mastercard" style="font-size: 24px; color: white;"></i>
                    <i class="fa-brands fa-cc-amex" style="font-size: 24px; color: white;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Starfield Animation Logic -->
    <script>
        const canvas = document.getElementById('starfield');
        const ctx = canvas.getContext('2d');
        let stars = [];
        const starCount = 180;

        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            initStars();
        }

        function initStars() {
            stars = [];
            for (let i = 0; i < starCount; i++) {
                stars.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    size: Math.random() * 1.5,
                    speed: Math.random() * 0.12 + 0.05,
                    opacity: Math.random() * 0.3 + 0.1,
                    twinkle: Math.random() * 0.01
                });
            }
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#000';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            stars.forEach(star => {
                star.y -= star.speed;
                if (star.y < 0) star.y = canvas.height;
                
                star.opacity += Math.sin(Date.now() * star.twinkle) * 0.01;
                ctx.fillStyle = `rgba(255, 255, 255, ${Math.max(0.1, Math.min(0.5, star.opacity))})`;
                ctx.beginPath();
                ctx.arc(star.x, star.y, star.size, 0, Math.PI * 2);
                ctx.fill();
            });
            requestAnimationFrame(animate);
        }

        window.addEventListener('resize', resize);
        resize();
        animate();
    </script>

    <script src="theme.js"></script>
    <script src="popup.js"></script>
    <script src="notifications.js"></script>

    <script>
        const xpointsInput = document.getElementById('xpointsInput');
        const calcResult = document.getElementById('calcResult');
        const costValue = document.getElementById('costValue');
        const customBuySection = document.getElementById('customBuySection');
        const customXpAmount = document.getElementById('customXpAmount');
        const customCostAmount = document.getElementById('customCostAmount');
        const customBuyBtn = document.getElementById('customBuyBtn');
        
        let customPaypalRendered = false;
        let currentCustomAmount = 0;
        let currentCustomCost = '0.00';

        xpointsInput.addEventListener('input', (e) => {
            const amount = parseInt(e.target.value);
            if (amount > 0) {
                const cost = (amount / 100).toFixed(2);
                costValue.textContent = cost;
                calcResult.classList.add('show');
                
                // Update custom buy section
                currentCustomAmount = amount;
                currentCustomCost = cost;
                customXpAmount.textContent = amount.toLocaleString();
                customCostAmount.textContent = cost;
                customBuySection.style.display = 'block';
                
                // Render PayPal button for custom amount (only once per amount change)
                renderCustomPayPalButton(amount, cost);
            } else {
                calcResult.classList.remove('show');
                customBuySection.style.display = 'none';
            }
        });

        // Handle Enter key to trigger purchase
        xpointsInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const amount = parseInt(xpointsInput.value);
                if (amount > 0) {
                    const cost = (amount / 100).toFixed(2);
                    // Open the card modal for custom amount
                    openCardModal({ id: 'custom', amount: cost, xp: amount });
                }
            }
        });

        // Custom buy button click
        customBuyBtn.addEventListener('click', () => {
            if (currentCustomAmount > 0) {
                openCardModal({ id: 'custom', amount: currentCustomCost, xp: currentCustomAmount });
            }
        });

        function renderCustomPayPalButton(xp, amount) {
            const container = document.getElementById('paypal-button-custom');
            container.innerHTML = ''; // Clear previous button
            
            paypal.Buttons({
                style: {
                    layout: 'vertical',
                    color: 'gold',
                    shape: 'pill',
                    label: 'paypal'
                },
                createOrder: (data, actions) => {
                    return actions.order.create({
                        purchase_units: [{
                            amount: { value: amount },
                            description: xp + " XPoints (Custom)"
                        }]
                    });
                },
                onApprove: (data, actions) => {
                    return actions.order.capture().then((details) => {
                        captureOrder(data.orderID, xp, amount, details.payer.name.given_name);
                    });
                }
            }).render('#paypal-button-custom');
        }


        // PayPal Integration
        const bundles = [
            { id: 'test', amount: '0.01', xp: 1 },
            { id: '100', amount: '1.00', xp: 100 },
            { id: '500', amount: '4.50', xp: 500 },
            { id: '1000', amount: '8.00', xp: 1000 },
            { id: '5000', amount: '35.00', xp: 5000 },
            { id: '10000', amount: '60.00', xp: 10000 }
        ];

        let selectedBundle = null;

        bundles.forEach(bundle => {
            paypal.Buttons({
                style: {
                    layout: 'vertical',
                    color:  'gold',
                    shape:  'pill',
                    label:  'paypal'
                },
                createOrder: (data, actions) => {
                    return actions.order.create({
                        purchase_units: [{
                            amount: {
                                value: bundle.amount
                            },
                            description: bundle.xp + " XPoints Pack"
                        }]
                    });
                },
                onApprove: (data, actions) => {
                    return actions.order.capture().then((details) => {
                        captureOrder(data.orderID, bundle.xp, bundle.amount, details.payer.name.given_name);
                    });
                }
            }).render('#paypal-button-' + bundle.id);

            // Add a "Card" button manually below each PayPal button to trigger our custom modal
            const container = document.getElementById('paypal-button-' + bundle.id);
            const cardBtn = document.createElement('button');
            cardBtn.className = 'card-trigger-btn';
            cardBtn.style.marginTop = '10px';
            cardBtn.innerHTML = '<i class="fa-solid fa-credit-card"></i> Debit or Credit Card';
            cardBtn.onclick = () => openCardModal(bundle);
            container.after(cardBtn);
        });

        // Modal Logic
        const overlay = document.getElementById('cardModalOverlay');
        const modalClose = document.getElementById('modalClose');

        function openCardModal(bundle) {
            selectedBundle = bundle;
            const amtEl = document.getElementById('modalPayAmount');
            const amtBtnEl = document.getElementById('modalPayAmountBtn');
            const xpEl = document.getElementById('modalXPCount');
            
            if (amtEl) amtEl.textContent = bundle.amount;
            if (amtBtnEl) amtBtnEl.textContent = bundle.amount;
            if (xpEl) {
                // Format for modal: 1000 -> 1k
                let val = bundle.xp;
                if(val >= 1000000000) val = (val/1000000000).toFixed(1) + 't';
                else if(val >= 1000000) val = (val/1000000).toFixed(1) + 'm';
                else if(val >= 1000) val = (val/1000).toFixed(1) + 'k';
                xpEl.textContent = val.toString().replace('.0', '');
            }
            
            overlay.classList.add('active');
            document.body.classList.add('modal-open');
            overlay.scrollTop = 0;
        }

        modalClose.onclick = () => {
            overlay.classList.remove('active');
            document.body.classList.remove('modal-open');
        };

        overlay.onclick = (e) => { 
            if(e.target === overlay) {
                overlay.classList.remove('active');
                document.body.classList.remove('modal-open');
            }
        };

        function initCardFields() {
            if (paypal.HostedFields && paypal.HostedFields.isEligible()) {
                paypal.HostedFields.render({
                    styles: {
                        'input': { 'font-size': '16px', 'font-family': 'sans-serif', 'color': '#ffffff' }
                    },
                    fields: {
                        number: { selector: '#card-number', placeholder: 'Card Number' },
                        cvv: { selector: '#cvv', placeholder: 'CVV' },
                        expirationDate: { selector: '#expiration-date', placeholder: 'MM/YY' }
                    }
                }).then(function (hostedFieldsInstance) {
                    document.querySelector('#submit-button').addEventListener('click', function () {
                        if (!selectedBundle) return;
                        const btn = this;
                        btn.disabled = true;
                        btn.textContent = 'Processing...';

                        hostedFieldsInstance.tokenize().then(function (payload) {
                            return paypal.Buttons().createOrder({
                                purchase_units: [{ amount: { value: selectedBundle.amount } }]
                            }).then(orderID => {
                                return captureOrder(orderID, selectedBundle.xp, selectedBundle.amount, 'Card User');
                            });
                        }).catch(function (err) {
                            btn.disabled = false;
                            btn.textContent = 'Pay Now';
                        });
                    });
                }).catch(() => showCardFallback());
            } else {
                showCardFallback();
            }
        }

        function showCardFallback() {
            const form = document.getElementById('payment-form');
            form.innerHTML = '<div id="paypal-card-fallback"></div>';
            
            paypal.Buttons({
                fundingSource: paypal.FUNDING.CARD,
                style: {
                    layout: 'vertical',
                    color: 'white',
                    shape: 'pill',
                    label: 'pay'
                },
                createOrder: (data, actions) => {
                    return actions.order.create({
                        purchase_units: [{
                            amount: { value: selectedBundle.amount },
                            description: selectedBundle.xp + " XPoints Pack"
                        }]
                    });
                },
                onApprove: (data, actions) => {
                    return actions.order.capture().then(() => {
                        captureOrder(data.orderID, selectedBundle.xp, selectedBundle.amount, 'User');
                    });
                }
            }).render('#paypal-card-fallback');
        }

        initCardFields();

        function captureOrder(orderID, xp, amount, name) {
            return fetch('../backend/capture_paypal_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ orderID: orderID, points: xp, amount: amount })
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    location.reload(); // Simplest way to refresh balance and state
                } else {
                }
            });
        }

        // Add some premium hover effects globally
        document.querySelectorAll('.bundle-card').forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                card.style.setProperty('--mouse-x', `${x}px`);
                card.style.setProperty('--mouse-y', `${y}px`);
            });
        });
    </script>
</body>
</html>
