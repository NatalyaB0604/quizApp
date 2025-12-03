<?php
require_once __DIR__ . '/config.php';

$db = new Database();
$conn = $db->getConnection();

$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND status = 'unread'");
    $count_stmt->execute([$_SESSION['user_id']]);
    $unread_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
}
?>

<header>
    <a href="index.php" class="logo">quizzzApp</a>
    <nav>
        <ul>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <li><a href="play.php">Играть</a></li>
                <li><a href="catalog.php">Каталог викторин</a></li>
            <?php elseif ($_SESSION['role'] === 'admin'): ?>
                <li><a href="moderation.php">Модерация</a></li>
                <li><a href="catalog.php">Каталог викторин</a></li>
            <?php else: ?>
                <li><a href="play.php">Играть</a></li>
                <li><a href="my_quizzes.php">Мои викторины</a></li>
                <li><a href="catalog.php">Каталог викторин</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="profile-wrapper">
            <button class="profile-btn notification" id="profileBtn">
                <img src="assets/images/account_circle.svg" alt="Профиль">
                <?php if ($unread_count > 0 && $_SESSION['role'] !== 'admin'): ?>
                    <span class="badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </button>
            <ul class="dropdown" id="profileDropdown">
                <li><a href="profile.php">Личный кабинет</a></li>
                <?php if ($_SESSION['role'] !== 'admin'): ?>
                    <li class="header-notification-item">
                        <a href="notifications.php">Уведомления
                            <?php if ($unread_count > 0): ?>
                                <span class="dropdown-badge"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li><a href="moderation.php">Модерация</a></li>
                <?php else: ?>
                    <li><a href="my_quizzes.php">Мои викторины</a></li>
                    <li><a href="my_results.php">Мои результаты</a></li>
                <?php endif; ?>
                <li><a href="logout.php">Выход</a></li>
            </ul>
        </div>
    <?php else: ?>
        <a class="login-btn" href="login.php">Войти</a>
    <?php endif; ?>
</header>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const profileBtn = document.getElementById('profileBtn');
        const profileDropdown = document.getElementById('profileDropdown');

        if (profileBtn && profileDropdown) {
            profileBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                profileDropdown.classList.toggle('active');
            });

            document.addEventListener('click', function () {
                profileDropdown.classList.remove('active');
            });

            profileDropdown.addEventListener('click', function (event) {
                event.stopPropagation();
            });
        }

        function showToast(message, notificationId = null) {
            console.log('Showing toast:', message);
            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            toast.innerHTML = message.replace(/\n/g, '<br>');

            if (notificationId) {
                toast.setAttribute('data-notification-id', notificationId);
            }

            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background-color: #4e4e4eff;
                color: white;
                padding: 16px;
                border-radius: 12px 12px 0 12px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                opacity: 0;
                transform: translateY(20px);
                transition: opacity 0.3s, transform 0.3s;
                z-index: 1000;
                cursor: pointer;
                max-width: 350px;
                word-wrap: break-word;
            `;

            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '1';
                toast.style.transform = 'translateY(0)';
            }, 100);

            let toastTimeout;

            function startToastTimer() {
                toastTimeout = setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateY(20px)';
                    toast.addEventListener('transitionend', () => {
                        if (toast.parentNode) {
                            toast.remove();
                        }
                    }, { once: true });
                }, 10000);
            }

            function clearToastTimer() {
                if (toastTimeout) {
                    clearTimeout(toastTimeout);
                }
            }

            startToastTimer();

            toast.addEventListener('mouseenter', clearToastTimer);

            toast.addEventListener('mouseleave', startToastTimer);

            toast.addEventListener('click', function () {
                window.location.href = 'notifications.php';
            });
        }

        let lastNotificationId = parseInt(localStorage.getItem('lastNotificationId')) || 0;
        setInterval(() => {
            const fetchUrl = 'check_notifications.php';
            fetch(fetchUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Fetch error: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Received data:', data);
                    if (data.success) {
                        if (data.new_notifications.length > 0) {
                            const maxId = Math.max(...data.new_notifications.map(n => parseInt(n.id)));
                            if (maxId < lastNotificationId) {
                                console.log('Resetting lastNotificationId to 0 due to out-of-sync state');
                                lastNotificationId = 0;
                                localStorage.setItem('lastNotificationId', lastNotificationId);
                            }
                        }

                        const badge = document.querySelector('.profile-btn .badge');
                        const dropdownBadge = document.querySelector('.dropdown-badge');
                        const currentCount = badge ? parseInt(badge.textContent) : 0;
                        const profileBtnElement = document.getElementById('profileBtn');

                        if (data.count > currentCount) {
                            if (badge) {
                                badge.textContent = data.count;
                            } else if (profileBtnElement) {
                                const newBadge = document.createElement('span');
                                newBadge.className = 'badge';
                                newBadge.textContent = data.count;
                                profileBtnElement.appendChild(newBadge);
                            }

                            if (dropdownBadge) {
                                dropdownBadge.textContent = data.count;
                            } else {
                                const notificationLink = document.querySelector('.header-notification-item a');
                                if (notificationLink) {
                                    const newDropdownBadge = document.createElement('span');
                                    newDropdownBadge.className = 'dropdown-badge';
                                    newDropdownBadge.textContent = data.count;
                                    notificationLink.appendChild(newDropdownBadge);
                                }
                            }
                        }

                        data.new_notifications.forEach(notif => {
                            console.log('Checking notification:', notif.id, 'against lastID:', lastNotificationId);
                            if (parseInt(notif.id) > lastNotificationId) {
                                showToast(notif.message, notif.id);
                                lastNotificationId = parseInt(notif.id);
                                localStorage.setItem('lastNotificationId', lastNotificationId);
                            } else {
                                console.log('Skipped toast for ID:', notif.id);
                            }
                        });
                    } else {
                        console.error('Data error:', data.error);
                    }
                })
                .catch(error => console.error('Fetch error:', error));
        }, 5000);
    });
</script>
