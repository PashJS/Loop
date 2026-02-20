<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: loginb.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Poll - Loop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="layout.css">
    <style>
        .create-poll-wrapper {
            max-width: 600px;
            margin: 120px auto 60px;
            padding: 0 20px;
            animation: fadeInUp 0.8s ease;
        }

        .glass-card {
            background: rgba(15, 15, 20, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.5);
        }

        .header-title {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 25px;
            background: linear-gradient(135deg, #c07bff 0%, #9d00ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }

        .poll-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 14px 18px;
            color: #fff;
            font-size: 16px;
            outline: none;
            transition: 0.3s;
        }

        .poll-input:focus {
            border-color: #9d00ff;
            background: rgba(157, 0, 255, 0.05);
        }

        .options-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 25px;
        }

        .option-item {
            display: flex;
            gap: 10px;
        }

        .remove-opt {
            background: rgba(255, 77, 77, 0.1);
            border: 1px solid rgba(255, 77, 77, 0.2);
            color: #ff4d4d;
            width: 45px;
            border-radius: 10px;
            cursor: pointer;
            transition: 0.2s;
        }

        .remove-opt:hover {
            background: #ff4d4d;
            color: #fff;
        }

        .add-opt-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px dashed rgba(255, 255, 255, 0.2);
            color: #aaa;
            padding: 12px;
            border-radius: 12px;
            width: 100%;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
            margin-bottom: 30px;
        }

        .add-opt-btn:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            border-style: solid;
        }

        .publish-btn {
            background: linear-gradient(135deg, #9d00ff, #c07bff);
            color: #fff;
            border: none;
            padding: 16px;
            border-radius: 14px;
            font-weight: 800;
            width: 100%;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .publish-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(157, 0, 255, 0.4);
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="create-poll-wrapper">
        <div class="glass-card">
            <h1 class="header-title"><i class="fa-solid fa-chart-simple"></i> Create Community Poll</h1>
            
            <div class="input-group">
                <label class="input-label">Question</label>
                <input type="text" id="pollQuestion" class="poll-input" placeholder="What do you want to ask?">
            </div>

            <div class="input-group">
                <label class="input-label">Options</label>
                <div class="options-list" id="optionsList">
                    <div class="option-item">
                        <input type="text" class="poll-input option-input" placeholder="Option 1">
                        <button class="remove-opt" style="visibility:hidden;"><i class="fa-solid fa-times"></i></button>
                    </div>
                    <div class="option-item">
                        <input type="text" class="poll-input option-input" placeholder="Option 2">
                        <button class="remove-opt" style="visibility:hidden;"><i class="fa-solid fa-times"></i></button>
                    </div>
                </div>
                <button id="addOptionBtn" class="add-opt-btn"><i class="fa-solid fa-plus"></i> Add Option</button>
            </div>

            <button id="publishBtn" class="publish-btn">Publish Poll</button>
        </div>
    </div>

    <script src="theme.js"></script>
    <script>
        const optionsList = document.getElementById('optionsList');
        const addBtn = document.getElementById('addOptionBtn');
        const publishBtn = document.getElementById('publishBtn');

        addBtn.addEventListener('click', () => {
            const count = optionsList.children.length;
            if (count >= 5) {
                return;
            }

            const div = document.createElement('div');
            div.className = 'option-item';
            div.innerHTML = `
                <input type="text" class="poll-input option-input" placeholder="Option ${count + 1}">
                <button class="remove-opt"><i class="fa-solid fa-times"></i></button>
            `;
            
            div.querySelector('.remove-opt').onclick = () => div.remove();
            optionsList.appendChild(div);
        });

        publishBtn.addEventListener('click', async () => {
            const question = document.getElementById('pollQuestion').value.trim();
            const optionInputs = document.querySelectorAll('.option-input');
            const options = Array.from(optionInputs).map(i => i.value.trim()).filter(v => v !== '');

            if (!question) {
                return;
            }
            if (options.length < 2) {
                return;
            }

            publishBtn.disabled = true;
            publishBtn.textContent = 'Publishing...';

            try {
                const res = await fetch('../backend/createPoll.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ question, options })
                });
                const data = await res.json();
                
                if (data.success) {
                    window.location.href = 'accountmanagement.php?sector=polls';
                } else {
                    publishBtn.disabled = false;
                    publishBtn.textContent = 'Publish Poll';
                }
            } catch (err) {
                publishBtn.disabled = false;
                publishBtn.textContent = 'Publish Poll';
            }
        });
    </script>
</body>
</html>
