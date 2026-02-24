<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Delivery Confirmation - Surprise Moi</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #6C1A81;
            /* Brand Color */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: #FFFFFF;
            /* White */
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            max-width: 450px;
            width: 100%;
            padding: 32px 24px;
            border: 2px solid #6C1A81;
            /* subtle brand border */
        }

        .logo {
            text-align: center;
            margin-bottom: 24px;
        }

        .logo img {
            height: 56px;
            width: auto;
            display: inline-block;
            margin-bottom: 8px;
        }

        .logo p {
            color: #000000;
            /* Black */
            font-size: 14px;
            margin: 0;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            color: #2d3748;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 8px;
        }

        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 15px;
            font-size: 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            transition: all 0.3s;
        }

        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type="number"] {
            -moz-appearance: textfield;
            letter-spacing: 5px;
            text-align: center;
            font-weight: 600;
            font-size: 24px;
        }

        input:focus {
            outline: none;
            border-color: #6C1A81;
            /* Brand */
            box-shadow: 0 0 0 3px rgba(108, 26, 129, 0.08);
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: #FDC541;
            /* State Color */
            color: #000000;
            /* Black */
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: box-shadow 0.12s ease;
        }

        .btn:hover {
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
        }

        .btn:active {
            transform: none;
        }

        .btn:disabled {
            background: #FFFFFF;
            color: #6C1A81;
            border: 1px solid #e6e6e6;
            cursor: not-allowed;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .alert-success {
            background: #FDC541;
            /* State Color */
            color: #000000;
            /* Black */
            border: 1px solid rgba(0, 0, 0, 0.06);
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert.show {
            display: block;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loading {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
            margin-right: 10px;
            vertical-align: middle;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .helper-text {
            color: #718096;
            font-size: 12px;
            margin-top: 5px;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            display: none;
        }

        .success-icon.show {
            display: block;
            animation: scaleIn 0.5s ease;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }

            to {
                transform: scale(1);
            }
        }

        .order-details {
            background: #f7fafc;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            display: none;
        }

        .order-details.show {
            display: block;
        }

        .order-details p {
            margin: 5px 0;
            font-size: 14px;
            color: #2d3748;
        }

        .order-details strong {
            color: #1a202c;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="logo">
            <img src="/images/logo-purple.svg" alt="Surprise Moi" />
            <p>Delivery Confirmation</p>
        </div>

        <div id="successIcon" class="success-icon">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" fill="#FDC541" opacity="0.18" />
                <path d="M7 12L10 15L17 8" stroke="#000000" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" />
            </svg>
        </div>

        <div id="alertBox" class="alert"></div>

        <form id="confirmationForm">
            <div class="form-group">
                <label for="delivery_pin">4-Digit PIN</label>
                <input type="number" id="delivery_pin" name="delivery_pin" placeholder="0000" maxlength="4"
                    pattern="[0-9]{4}" required autofocus>
                <p class="helper-text">Enter the 4-digit PIN given by the customer</p>
            </div>

            <div class="form-group">
                <label for="order_number">Order Number</label>
                <input type="text" id="order_number" name="order_number" placeholder="VND-WAPZ-XXXX-XXXXXX-XX" required>
                <p class="helper-text">Enter the order number from the package</p>
            </div>

            <div class="form-group">
                <label for="delivery_person_name">Your Name (Optional)</label>
                <input type="text" id="delivery_person_name" name="delivery_person_name" placeholder="e.g., John Doe">
            </div>

            <button type="submit" class="btn" id="submitBtn">
                Confirm Delivery
            </button>
        </form>

        <div id="orderDetails" class="order-details"></div>
    </div>

    <script>
        const form = document.getElementById('confirmationForm');
        const submitBtn = document.getElementById('submitBtn');
        const alertBox = document.getElementById('alertBox');
        const successIcon = document.getElementById('successIcon');
        const orderDetails = document.getElementById('orderDetails');
        const pinInput = document.getElementById('delivery_pin');

        // Limit PIN input to 4 digits
        pinInput.addEventListener('input', function () {
            if (this.value.length > 4) {
                this.value = this.value.slice(0, 4);
            }
        });

        function showAlert(message, type) {
            alertBox.textContent = message;
            alertBox.className = `alert alert-${type} show`;
            setTimeout(() => {
                alertBox.classList.remove('show');
            }, 5000);
        }

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const pin = document.getElementById('delivery_pin').value;
            const orderNumber = document.getElementById('order_number').value;
            const deliveryPersonName = document.getElementById('delivery_person_name').value;

            // Validate PIN
            if (pin.length !== 4 || !/^\d{4}$/.test(pin)) {
                showAlert('Please enter a valid 4-digit PIN', 'error');
                return;
            }

            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading"></span>Confirming...';

            try {
                const response = await fetch('/api/v1/delivery/confirm', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        delivery_pin: pin,
                        order_number: orderNumber,
                        delivery_person_name: deliveryPersonName || 'Delivery Person'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Hide form
                    form.style.display = 'none';

                    // Show success
                    successIcon.classList.add('show');
                    showAlert(data.message, 'success');

                    // Show order details
                    if (data.order) {
                        orderDetails.innerHTML = `
                            <p><strong>Order:</strong> ${data.order.order_number}</p>
                            <p><strong>Amount:</strong> ${data.order.currency} ${data.order.total}</p>
                            <p><strong>Confirmed:</strong> ${new Date(data.order.confirmed_at).toLocaleString()}</p>
                        `;
                        orderDetails.classList.add('show');
                    }

                    // Reset form after 3 seconds
                    setTimeout(() => {
                        form.reset();
                        form.style.display = 'block';
                        successIcon.classList.remove('show');
                        orderDetails.classList.remove('show');
                    }, 3000);
                } else {
                    showAlert(data.message || 'Confirmation failed. Please check your entries.', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Network error. Please check your connection and try again.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Confirm Delivery';
            }
        });
    </script>
</body>

</html>
