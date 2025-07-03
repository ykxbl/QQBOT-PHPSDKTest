// 全局变量
let authToken = localStorage.getItem('authToken') || '';

// DOM元素
const loginContainer = document.getElementById('login-container');
const dashboard = document.getElementById('dashboard');
const passwordInput = document.getElementById('password');
const loginBtn = document.getElementById('login-btn');
const loginError = document.getElementById('login-error');
const robotListContainer = document.getElementById('robot-list-container');
const appidInput = document.getElementById('appid');
const appsecretInput = document.getElementById('appsecret');
const addRobotBtn = document.getElementById('add-robot-btn');
const addRobotMessage = document.getElementById('add-robot-message');

// 初始化
document.addEventListener('DOMContentLoaded', () => {
    if (authToken) {
        // 已有token，尝试直接显示仪表盘
        loginContainer.style.display = 'none';
        dashboard.style.display = 'block';
        fetchRobotList();
    } else {
        loginContainer.style.display = 'block';
        dashboard.style.display = 'none';
    }
});

// 登录功能
loginBtn.addEventListener('click', async () => {
    const password = passwordInput.value.trim();
    
    if (!password) {
        loginError.textContent = '请输入密码';
        return;
    }
    
    try {
        // 计算SHA256哈希
        const hashBuffer = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(`password-${password}`));
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        const token = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        
        const response = await apiRequest('api/login.php', { token });
        
        if (response.code === 200) {
            authToken = response.data.AT;
            localStorage.setItem('authToken', authToken);
            
            loginContainer.style.display = 'none';
            dashboard.style.display = 'block';
            loginError.textContent = '';
            
            fetchRobotList();
        } else {
            loginError.textContent = response.msg || '登录失败';
        }
    } catch (error) {
        console.error('登录错误:', error);
        loginError.textContent = '登录过程中发生错误';
    }
});

// 获取机器人列表
async function fetchRobotList() {
    try {
        const response = await apiRequest('api/robot_list.php', {});
        
        if (response.code === 200) {
            renderRobotList(response.data);
        } else if (response.code === 403 || response.code === 405) {
            // 鉴权失败或过期，返回登录页
            handleAuthError();
        } else {
            robotListContainer.innerHTML = `<div class="error">获取机器人列表失败: ${response.msg}</div>`;
        }
    } catch (error) {
        console.error('获取机器人列表错误:', error);
        robotListContainer.innerHTML = '<div class="error">获取机器人列表时发生错误</div>';
    }
}

// 渲染机器人列表
function renderRobotList(robots) {
    if (!robots || robots.length === 0) {
        robotListContainer.innerHTML = '<div>暂无机器人</div>';
        return;
    }
    
    let html = '';
    robots.forEach(robot => {
        html += `
            <div class="robot-item">
                <span>机器人ID: ${robot}</span>
            </div>
        `;
    });
    
    robotListContainer.innerHTML = html;
}

// 添加机器人
addRobotBtn.addEventListener('click', async () => {
    const appid = appidInput.value.trim();
    const appsecret = appsecretInput.value.trim();
    
    if (!appid || !appsecret) {
        addRobotMessage.textContent = '请填写完整的机器人信息';
        addRobotMessage.className = 'error';
        return;
    }
    
    try {
        const response = await apiRequest('api/robot_add.php', {
            appid,
            appsecret
        });
        
        if (response.code === 200) {
            addRobotMessage.textContent = '机器人添加成功';
            addRobotMessage.className = 'success';
            appidInput.value = '';
            appsecretInput.value = '';
            
            // 刷新机器人列表
            fetchRobotList();
        } else {
            addRobotMessage.textContent = response.msg || '添加机器人失败';
            addRobotMessage.className = 'error';
        }
    } catch (error) {
        console.error('添加机器人错误:', error);
        addRobotMessage.textContent = '添加机器人时发生错误';
        addRobotMessage.className = 'error';
    }
});

// 处理鉴权错误
function handleAuthError() {
    authToken = '';
    localStorage.removeItem('authToken');
    loginContainer.style.display = 'block';
    dashboard.style.display = 'none';
    loginError.textContent = '会话已过期，请重新登录';
}

// 通用API请求函数
async function apiRequest(endpoint, data) {
    const requestData = {
        ...data,
        AT: authToken
    };
    
    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestData)
        });
        
        return await response.json();
    } catch (error) {
        console.error('API请求错误:', error);
        return {
            code: 500,
            msg: '网络请求失败'
        };
    }
}