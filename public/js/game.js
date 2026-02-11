/**
 * 谁是卧底 - 前端游戏逻辑
 */

(function() {
    'use strict';

    // ==================== 配置 ====================
    // API 地址 - 根据当前路径自动计算
    const API_URL = window.location.pathname.replace(/\/public\/?.*$/, '') + '/api/join.php';
    // WebSocket 地址 - 使用当前域名，端口 2346
    const WS_PORT = 2346;
    const WS_URL = `ws://${window.location.hostname}:${WS_PORT}`;

    // ==================== 状态 ====================
    let ws = null;
    let gameState = {
        room: '',
        name: '',
        seat: 0,
        role: '',
        word: '',
        isAdmin: false,
        status: 'waiting',
        speaker: null,
        players: [],
        votes: {},
        hasVoted: false,
        speakTime: 0,  // 发言剩余时间
        firstSpeaker: null  // 第一个发言人座位号
    };

    // 倒计时定时器
    let countdownTimer = null;

    // ==================== DOM 元素 ====================
    const elements = {
        // 登录页
        loginPage: document.getElementById('login-page'),
        gamePage: document.getElementById('game-page'),
        roomInput: document.getElementById('room-input'),
        nameInput: document.getElementById('name-input'),
        joinBtn: document.getElementById('join-btn'),
        loginError: document.getElementById('login-error'),

        // 游戏页
        roomId: document.getElementById('room-id'),
        roomStatus: document.getElementById('room-status'),
        playerSeat: document.getElementById('player-seat'),
        playerName: document.getElementById('player-name'),
        
        // 身份卡片
        identityCard: document.getElementById('identity-card'),
        myRole: document.getElementById('my-role'),
        myWord: document.getElementById('my-word'),

        // 发言人
        speakerBox: document.getElementById('speaker-box'),
        currentSpeaker: document.getElementById('current-speaker'),

        // 玩家列表
        playersList: document.getElementById('players-list'),

        // 投票
        voteSection: document.getElementById('vote-section'),
        voteList: document.getElementById('vote-list'),
        voteResult: document.getElementById('vote-result'),

        // 管理员
        adminPanel: document.getElementById('admin-panel'),
        btnStart: document.getElementById('btn-start'),
        btnNext: document.getElementById('btn-next'),
        btnVoteStart: document.getElementById('btn-vote-start'),
        btnEnd: document.getElementById('btn-end'),
        killList: document.getElementById('kill-list'),

        // 聊天
        chatBox: document.getElementById('chat-box'),
        chatInput: document.getElementById('chat-input'),
        chatSend: document.getElementById('chat-send')
    };

    // ==================== 初始化 ====================
    function init() {
        // 绑定事件
        elements.joinBtn.addEventListener('click', handleJoin);
        elements.roomInput.addEventListener('keypress', e => e.key === 'Enter' && elements.nameInput.focus());
        elements.nameInput.addEventListener('keypress', e => e.key === 'Enter' && handleJoin());
        
        elements.identityCard.addEventListener('click', () => {
            elements.identityCard.classList.toggle('flipped');
        });

        elements.chatSend.addEventListener('click', sendChat);
        elements.chatInput.addEventListener('keypress', e => e.key === 'Enter' && sendChat());

        // 管理员按钮
        elements.btnStart.addEventListener('click', () => sendAction('start'));
        elements.btnNext.addEventListener('click', () => sendAction('next'));
        elements.btnVoteStart.addEventListener('click', () => sendAction('vote_start'));
        elements.btnEnd.addEventListener('click', () => {
            if (confirm('确定要结束本局游戏吗？')) {
                sendAction('end');
            }
        });

        // 检查是否有保存的会话
        const savedRoom = sessionStorage.getItem('room');
        const savedName = sessionStorage.getItem('name');
        if (savedRoom && savedName) {
            elements.roomInput.value = savedRoom;
            elements.nameInput.value = savedName;
        }
    }

    // ==================== 加入房间 ====================
    async function handleJoin() {
        const room = elements.roomInput.value.trim().toUpperCase();
        const name = elements.nameInput.value.trim();

        if (!room) {
            showError('请输入房间号');
            return;
        }
        if (!name) {
            showError('请输入昵称');
            return;
        }

        elements.joinBtn.disabled = true;
        elements.loginError.textContent = '';

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ room, name })
            });

            const data = await response.json();

            if (!data.success) {
                showError(data.error || '加入失败');
                elements.joinBtn.disabled = false;
                return;
            }

            // 保存状态
            gameState.room = room;
            gameState.name = name;
            gameState.seat = data.seat;
            gameState.role = data.role;
            gameState.word = data.word;
            gameState.isAdmin = data.isAdmin;

            // 保存到 sessionStorage
            sessionStorage.setItem('room', room);
            sessionStorage.setItem('name', name);

            // 连接 WebSocket
            connectWebSocket();

        } catch (err) {
            showError('网络错误，请重试');
            elements.joinBtn.disabled = false;
        }
    }

    function showError(msg) {
        elements.loginError.textContent = msg;
    }

    // ==================== WebSocket ====================
    function connectWebSocket() {
        const url = `${WS_URL}?room=${encodeURIComponent(gameState.room)}&name=${encodeURIComponent(gameState.name)}`;
        
        ws = new WebSocket(url);

        ws.onopen = () => {
            console.log('WebSocket 已连接');
            enterGame();
        };

        ws.onmessage = (event) => {
            const data = JSON.parse(event.data);
            handleMessage(data);
        };

        ws.onclose = () => {
            console.log('WebSocket 已断开');
            // 尝试重连
            setTimeout(() => {
                if (gameState.status !== 'ended') {
                    connectWebSocket();
                }
            }, 3000);
        };

        ws.onerror = (err) => {
            console.error('WebSocket 错误:', err);
        };
    }

    function sendAction(action, data = {}) {
        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({ action, ...data }));
        }
    }

    function sendChat() {
        const msg = elements.chatInput.value.trim();
        if (!msg) return;

        sendAction('chat', { msg });
        elements.chatInput.value = '';
    }

    // ==================== 进入游戏 ====================
    function enterGame() {
        // 切换页面
        elements.loginPage.classList.remove('active');
        elements.gamePage.classList.add('active');

        // 显示房间信息
        elements.roomId.textContent = `房间: ${gameState.room}`;
        
        if (gameState.isAdmin) {
            elements.playerSeat.textContent = '管理员';
            elements.playerName.textContent = gameState.name;
            elements.identityCard.style.display = 'none';
            elements.adminPanel.classList.remove('hidden');
        } else {
            elements.playerSeat.textContent = `${gameState.seat}号`;
            elements.playerName.textContent = gameState.name;
            
            // 设置身份卡片
            elements.myRole.textContent = gameState.role;
            elements.myRole.className = 'role ' + (gameState.role === '卧底' ? 'spy' : 'civilian');
            elements.myWord.textContent = gameState.word;
        }

        updateStatus();
    }

    // ==================== 消息处理 ====================
    function handleMessage(data) {
        switch (data.type) {
            case 'state':
                gameState.status = data.status;
                gameState.speaker = data.speaker;
                gameState.speakTime = data.speakTime || 0;
                gameState.firstSpeaker = data.firstSpeaker || null;
                updateStatus();
                updateSpeaker();
                startCountdown();
                break;

            case 'players':
                gameState.players = data.list;
                updatePlayersList();
                updateKillList();
                break;

            case 'chat':
                addChatMessage(data.msg);
                break;

            case 'votes':
                gameState.votes = data.data;
                updateVotes();
                break;

            case 'countdown':
                gameState.speakTime = data.time;
                updateCountdownDisplay();
                break;
        }
    }

    // ==================== UI 更新 ====================
    function updateStatus() {
        const statusMap = {
            'waiting': { text: '等待中', class: 'status-waiting' },
            'started': { text: '游戏中', class: 'status-started' },
            'voting': { text: '投票中', class: 'status-voting' },
            'ended': { text: '已结束', class: 'status-ended' }
        };

        const status = statusMap[gameState.status] || statusMap['waiting'];
        elements.roomStatus.textContent = status.text;
        elements.roomStatus.className = 'status-badge ' + status.class;

        // 显示/隐藏投票区域
        if (gameState.status === 'voting' && !gameState.isAdmin) {
            elements.voteSection.classList.remove('hidden');
            updateVoteList();
        } else if (gameState.status !== 'voting') {
            elements.voteSection.classList.add('hidden');
            gameState.hasVoted = false;
        }

        // 更新管理员按钮状态
        if (gameState.isAdmin) {
            elements.btnStart.disabled = gameState.status !== 'waiting';
            elements.btnNext.disabled = gameState.status !== 'started';
            elements.btnVoteStart.disabled = gameState.status !== 'started';
            elements.btnEnd.disabled = gameState.status === 'ended';
        }
    }

    function updateSpeaker() {
        if (gameState.status === 'started' && gameState.speaker) {
            elements.speakerBox.classList.remove('hidden');
            
            const player = gameState.players.find(p => p.seat === gameState.speaker);
            const name = player ? player.name : `座位${gameState.speaker}`;
            
            // 显示倒计时
            const timeText = gameState.speakTime > 0 ? ` (${gameState.speakTime}秒)` : '';
            elements.currentSpeaker.textContent = `${gameState.speaker}号 ${name}${timeText}`;
        } else {
            elements.speakerBox.classList.add('hidden');
            stopCountdown();
        }

        // 更新玩家列表中的发言状态
        updatePlayersList();
    }

    // 启动倒计时
    function startCountdown() {
        stopCountdown();
        if (gameState.status === 'started' && gameState.speakTime > 0) {
            countdownTimer = setInterval(() => {
                if (gameState.speakTime > 0) {
                    gameState.speakTime--;
                    updateCountdownDisplay();
                } else {
                    stopCountdown();
                }
            }, 1000);
        }
    }

    // 停止倒计时
    function stopCountdown() {
        if (countdownTimer) {
            clearInterval(countdownTimer);
            countdownTimer = null;
        }
    }

    // 更新倒计时显示
    function updateCountdownDisplay() {
        if (gameState.status === 'started' && gameState.speaker) {
            const player = gameState.players.find(p => p.seat === gameState.speaker);
            const name = player ? player.name : `座位${gameState.speaker}`;
            const timeText = gameState.speakTime > 0 ? ` (${gameState.speakTime}秒)` : '';
            elements.currentSpeaker.textContent = `${gameState.speaker}号 ${name}${timeText}`;
        }
    }

    function updatePlayersList() {
        elements.playersList.innerHTML = '';

        gameState.players.forEach(player => {
            const div = document.createElement('div');
            div.className = 'player-item';
            
            if (player.alive) {
                div.classList.add('alive');
            } else {
                div.classList.add('dead');
            }

            if (player.seat === gameState.speaker && gameState.status === 'started') {
                div.classList.add('speaking');
            }

            // 管理员可以看到身份和词语
            let identityHtml = '';
            if (gameState.isAdmin && player.role && player.word) {
                const roleClass = player.role === '卧底' ? 'spy' : 'civilian';
                identityHtml = `
                    <div class="player-role ${roleClass}">${player.role}</div>
                    <div class="player-word">${player.word}</div>
                `;
            }

            div.innerHTML = `
                <div class="seat">${player.seat}号</div>
                <div class="name">${escapeHtml(player.name)}</div>
                ${identityHtml}
            `;

            elements.playersList.appendChild(div);
        });
    }

    function updateVoteList() {
        elements.voteList.innerHTML = '';

        gameState.players.forEach(player => {
            if (!player.alive) return;
            if (player.seat === gameState.seat) return; // 不能投自己

            const div = document.createElement('div');
            div.className = 'vote-item';
            if (gameState.hasVoted) {
                div.style.pointerEvents = 'none';
            }

            const voteCount = gameState.votes[player.seat] || 0;

            div.innerHTML = `
                <div class="seat">${player.seat}号</div>
                <div class="name">${escapeHtml(player.name)}</div>
                <div class="vote-count">${voteCount}票</div>
            `;

            div.addEventListener('click', () => {
                if (!gameState.hasVoted) {
                    sendAction('vote', { target: player.seat });
                    gameState.hasVoted = true;
                    div.classList.add('voted');
                    updateVoteList();
                }
            });

            elements.voteList.appendChild(div);
        });
    }

    function updateVotes() {
        // 更新投票列表中的票数
        const items = elements.voteList.querySelectorAll('.vote-item');
        items.forEach(item => {
            const seatText = item.querySelector('.seat').textContent;
            const seat = parseInt(seatText);
            const count = gameState.votes[seat] || 0;
            item.querySelector('.vote-count').textContent = `${count}票`;
        });

        // 更新管理员的投票结果显示
        if (gameState.isAdmin && gameState.status === 'voting') {
            let resultHtml = '<h4>当前票数：</h4>';
            gameState.players.forEach(player => {
                if (!player.alive) return;
                const count = gameState.votes[player.seat] || 0;
                resultHtml += `<div>${player.seat}号 ${escapeHtml(player.name)}: ${count}票</div>`;
            });
            elements.voteResult.innerHTML = resultHtml;
            elements.voteSection.classList.remove('hidden');
        }
    }

    function updateKillList() {
        if (!gameState.isAdmin) return;

        elements.killList.innerHTML = '';

        gameState.players.forEach(player => {
            if (!player.alive) return;

            const btn = document.createElement('button');
            btn.className = 'kill-btn';
            btn.textContent = `淘汰 ${player.seat}号 ${player.name}`;
            btn.addEventListener('click', () => {
                if (confirm(`确定要淘汰 ${player.seat}号 ${player.name} 吗？`)) {
                    sendAction('kill', { seat: player.seat });
                }
            });

            elements.killList.appendChild(btn);
        });
    }

    function addChatMessage(msg) {
        const div = document.createElement('div');
        div.className = 'chat-msg';
        
        if (msg.name === '系统') {
            div.classList.add('system');
        }

        div.innerHTML = `
            <div class="meta">
                <span class="seat-tag">${msg.seat === 0 ? '📢' : msg.seat + '号'}</span>
                <span class="name">${escapeHtml(msg.name)}</span>
                <span class="time">${msg.time}</span>
            </div>
            <div class="content">${escapeHtml(msg.msg)}</div>
        `;

        elements.chatBox.appendChild(div);
        elements.chatBox.scrollTop = elements.chatBox.scrollHeight;
    }

    // ==================== 工具函数 ====================
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ==================== 启动 ====================
    init();

})();
