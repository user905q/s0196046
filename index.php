<?php
header('Content-Type: text/html; charset=UTF-8');

$db_user = 'u82327';
$db_pass = '2458481'; 
$db_name = 'u82327';
$db_host = 'localhost';

$errors = [];
$success = false;

function utf8_strlen($string) {
    return preg_match_all('/./us', $string, $matches);
}

function saveApplication($pdo, $data) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO applications (full_name, phone, email, birth_date, gender, biography, contract_agreed) 
                               VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, :contract_agreed)");
        $stmt->execute([
            ':full_name' => $data['full_name'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':birth_date' => $data['birth_date'],
            ':gender' => $data['gender'],
            ':biography' => $data['biography'],
            ':contract_agreed' => isset($data['contract_agreed']) ? 1 : 0
        ]);
        
        $application_id = $pdo->lastInsertId();

        if (!empty($data['languages']) && is_array($data['languages'])) {
            $stmt_lang = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
            foreach ($data['languages'] as $lang_id) {
                $stmt_lang->execute([$application_id, $lang_id]);
            }
        }

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        return false;
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    if (empty($full_name)) {
        $errors['full_name'] = 'Поле ФИО обязательно для заполнения.';
    } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $full_name)) {
        $errors['full_name'] = 'ФИО должно содержать только буквы, пробелы и дефис.';
    } elseif (utf8_strlen($full_name) > 150) {
        $errors['full_name'] = 'ФИО не должно превышать 150 символов.';
    }

    $phone = trim($_POST['phone'] ?? '');
    if (empty($phone)) {
        $errors['phone'] = 'Поле Телефон обязательно.';
    } elseif (!preg_match('/^[\d\s\(\)\+\-]+$/', $phone)) {
        $errors['phone'] = 'Телефон содержит недопустимые символы.';
    }

    $email = trim($_POST['email'] ?? '');
    if (empty($email)) {
        $errors['email'] = 'Поле E-mail обязательно.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный E-mail.';
    }

    $birth_date = $_POST['birth_date'] ?? '';
    if (empty($birth_date)) {
        $errors['birth_date'] = 'Выберите дату рождения.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        $errors['birth_date'] = 'Неверный формат даты.';
    } else {
        $date_parts = explode('-', $birth_date);
        if (count($date_parts) == 3) {
            $year = (int)$date_parts[0];
            $month = (int)$date_parts[1];
            $day = (int)$date_parts[2];
            if (!checkdate($month, $day, $year)) {
                $errors['birth_date'] = 'Укажите существующую дату.';
            } elseif ($year < 1900 || $year > date('Y')) {
                $errors['birth_date'] = 'Укажите корректный год рождения.';
            }
        }
    }

    $gender = $_POST['gender'] ?? '';
    if (!in_array($gender, ['male', 'female'], true)) {
        $errors['gender'] = 'Выберите пол.';
    }

    $languages = $_POST['languages'] ?? [];
    if (empty($languages)) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования.';
    } else {
        foreach ($languages as $lang_id) {
            if (!is_numeric($lang_id) || (int)$lang_id <= 0) {
                $errors['languages'] = 'Некорректный выбор языка программирования.';
                break;
            }
        }
    }

    $biography = trim($_POST['biography'] ?? '');
    if (empty($biography)) {
        $errors['biography'] = 'Расскажите немного о себе в биографии.';
    } elseif (utf8_strlen($biography) > 5000) {
        $errors['biography'] = 'Биография не должна превышать 5000 символов.';
    }

    if (!isset($_POST['contract_agreed'])) {
        $errors['contract_agreed'] = 'Необходимо ознакомиться с контрактом.';
    }

    if (empty($errors)) {
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            if (saveApplication($pdo, $_POST)) {
                $success = true;
                $_POST = [];
            } else {
                $errors['db'] = 'Ошибка сохранения в базу данных.';
            }
        } catch (PDOException $e) {
            $errors['db'] = 'Ошибка подключения к БД: ' . $e->getMessage();
        }
    }
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $lang_stmt = $pdo->query("SELECT id, name FROM programming_languages ORDER BY name");
    $all_languages = $lang_stmt->fetchAll();
} catch (PDOException $e) {
    die("DB Connection Error: " . $e->getMessage());
}


function old($field) {
    if (!isset($_POST[$field])) {
        return '';
    }
    return htmlspecialchars($_POST[$field], ENT_QUOTES, 'UTF-8');
}

function isLanguageChecked($lang_id) {
    return isset($_POST['languages']) && is_array($_POST['languages']) && in_array($lang_id, $_POST['languages']);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Форма заявки</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Заполните анкету</h1>

        <?php if ($success): ?>
            <div class="message success">Спасибо! Ваши данные успешно сохранены.</div>
        <?php elseif (!empty($errors['db'])): ?>
            <div class="message error"><?php echo htmlspecialchars($errors['db'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form action="" method="POST" class="application-form">
            <div class="form-group <?php echo isset($errors['full_name']) ? 'has-error' : ''; ?>">
                <label for="full_name">ФИО:</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo old('full_name'); ?>" maxlength="150">
                <?php if (isset($errors['full_name'])): ?>
                    <span class="error-message"><?php echo htmlspecialchars($errors['full_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group <?php echo isset($errors['phone']) ? 'has-error' : ''; ?>">
                <label for="phone">Телефон:</label>
                <input type="tel" id="phone" name="phone" value="<?php echo old('phone'); ?>">
                <?php if (isset($errors['phone'])): ?>
                    <span class="error-message"><?php echo htmlspecialchars($errors['phone'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group <?php echo isset($errors['email']) ? 'has-error' : ''; ?>">
                <label for="email">E-mail:</label>
                <input type="email" id="email" name="email" value="<?php echo old('email'); ?>">
                <?php if (isset($errors['email'])): ?>
                    <span class="error-message"><?php echo htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group <?php echo isset($errors['birth_date']) ? 'has-error' : ''; ?>">
                <label for="birth_date">Дата рождения:</label>
                <input type="date" id="birth_date" name="birth_date" value="<?php echo old('birth_date'); ?>">
                <?php if (isset($errors['birth_date'])): ?>
                    <span class="error-message"><?php echo htmlspecialchars($errors['birth_date'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group <?php echo isset($errors['gender']) ? 'has-error' : ''; ?>">
                <label>Пол:</label>
                <div class="radio-group">
                    <label><input type="radio" name="gender" value="male" <?php echo old('gender') == 'male' ? 'checked' : ''; ?>> Мужской</label>
                    <label><input type="radio" name="gender" value="female" <?php echo old('gender') == 'female' ? 'checked' : ''; ?>> Женский</label>
                </div>
                <?php if (isset($errors['gender'])): ?>
                    <span class="error-message"><?php echo htmlspecialchars($errors['gender'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group <?php echo isset($errors['languages']) ? 'has-error' : ''; ?>">
                <label>Любимые языки программирования:</label>
                <div class="checkbox-group">
                    <?php foreach ($all_languages as $lang): ?>
                        <label>
                            <input type="checkbox" name="languages[]" value="<?php echo htmlspecialchars($lang['id'], ENT_QUOTES, 'UTF-8'); ?>" 
                                   <?php echo isLanguageChecked($lang['id']) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($lang['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($errors['languages'])): ?>
                    <span class="error-message"><?php echo htmlspecialchars($errors['languages'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group <?php echo isset($errors['biography']) ? 'has-error' : ''; ?>">
                <label for="biography">Биография:</label>
                <textarea id="biography" name="biography" rows="5"><?php echo old('biography'); ?></textarea>
                <?php if (isset($errors['biography'])): ?>
                    <span class="error-message"><?php echo htmlspecialchars($errors['biography'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group <?php echo isset($errors['contract_agreed']) ? 'has-error' : ''; ?>">
                <label>
                    <input type="checkbox" name="contract_agreed" value="1" <?php echo isset($_POST['contract_agreed']) ? 'checked' : ''; ?>>
                    С контрактом ознакомлен(а)
                </label>
                <?php if (isset($errors['contract_agreed'])): ?>
                    <span class="error-message"><?php echo htmlspecialchars($errors['contract_agreed'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="submit-btn">Сохранить</button>
        </form>
    </div>
</body>
</html>