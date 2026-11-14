-- ============================================================
--  ШоуБум — база данных «Услуги аниматоров»
--  Импорт: phpMyAdmin → база «Услуги аниматоров» → Импорт.
--  Скрипт создаёт таблицы, 5 представлений и демо-данные.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `Услуги аниматоров`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `Услуги аниматоров`;

-- ---------- Пользователи ----------
CREATE TABLE IF NOT EXISTS users (
  user_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(100) NOT NULL,
  email      VARCHAR(150) NOT NULL,
  phone      VARCHAR(30)  NOT NULL,
  password   VARCHAR(255) NOT NULL,
  role       ENUM('customer','animator','admin') NOT NULL DEFAULT 'customer',
  avatar     VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Аниматоры ----------
CREATE TABLE IF NOT EXISTS animators (
  animator_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id          INT UNSIGNED NOT NULL,
  specialization   VARCHAR(100) NOT NULL DEFAULT 'Детские праздники',
  experience_years TINYINT UNSIGNED NOT NULL DEFAULT 0,
  hourly_rate      DECIMAL(10,2) NOT NULL DEFAULT 2500.00,
  rating           DECIMAL(2,1) NOT NULL DEFAULT 5.0,
  bio              TEXT,
  city             VARCHAR(100) NOT NULL DEFAULT 'Москва',
  photo            VARCHAR(255) DEFAULT NULL,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (animator_id),
  UNIQUE KEY uq_animators_user (user_id),
  CONSTRAINT fk_animators_user FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Возрастные группы ----------
CREATE TABLE IF NOT EXISTS age_groups (
  age_group_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title        VARCHAR(50) NOT NULL,
  min_age      TINYINT UNSIGNED NOT NULL,
  max_age      TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (age_group_id),
  UNIQUE KEY uq_age_groups_title (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS animator_age_groups (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  animator_id  INT UNSIGNED NOT NULL,
  age_group_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_animator_age (animator_id, age_group_id),
  CONSTRAINT fk_aag_animator FOREIGN KEY (animator_id) REFERENCES animators (animator_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_aag_age FOREIGN KEY (age_group_id) REFERENCES age_groups (age_group_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Тематические программы ----------
CREATE TABLE IF NOT EXISTS programs (
  program_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title        VARCHAR(150) NOT NULL,
  category     VARCHAR(100) NOT NULL DEFAULT 'Детские праздники',
  description  TEXT,
  price        DECIMAL(10,2) NOT NULL DEFAULT 2500.00,
  duration_min SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  image        VARCHAR(255) DEFAULT NULL,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (program_id),
  KEY idx_programs_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS animator_programs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  animator_id INT UNSIGNED NOT NULL,
  program_id  INT UNSIGNED NOT NULL,
  price       DECIMAL(10,2) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_animator_program (animator_id, program_id),
  CONSTRAINT fk_ap_animator FOREIGN KEY (animator_id) REFERENCES animators (animator_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ap_program FOREIGN KEY (program_id) REFERENCES programs (program_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Бронирования ----------
CREATE TABLE IF NOT EXISTS bookings (
  booking_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id    INT UNSIGNED DEFAULT NULL,
  animator_id    INT UNSIGNED NOT NULL,
  program_id     INT UNSIGNED DEFAULT NULL,
  contact_name   VARCHAR(100) NOT NULL,
  contact_phone  VARCHAR(30) NOT NULL,
  contact_email  VARCHAR(150) DEFAULT NULL,
  event_date     DATE NOT NULL,
  event_time     TIME NOT NULL,
  duration_hours DECIMAL(3,1) NOT NULL DEFAULT 1.0,
  address        VARCHAR(255) NOT NULL,
  children_age   VARCHAR(50) DEFAULT NULL,
  comment        TEXT,
  status         ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  total_price    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (booking_id),
  KEY idx_bookings_animator_date (animator_id, event_date, event_time),
  KEY idx_bookings_customer (customer_id),
  CONSTRAINT fk_bookings_customer FOREIGN KEY (customer_id) REFERENCES users (user_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_bookings_animator FOREIGN KEY (animator_id) REFERENCES animators (animator_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_bookings_program FOREIGN KEY (program_id) REFERENCES programs (program_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Отзывы ----------
CREATE TABLE IF NOT EXISTS reviews (
  review_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id  INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  animator_id INT UNSIGNED NOT NULL,
  rating      TINYINT UNSIGNED NOT NULL,
  text        TEXT NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (review_id),
  UNIQUE KEY uq_reviews_booking (booking_id),
  KEY idx_reviews_animator (animator_id),
  CONSTRAINT fk_reviews_booking FOREIGN KEY (booking_id) REFERENCES bookings (booking_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reviews_customer FOREIGN KEY (customer_id) REFERENCES users (user_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reviews_animator FOREIGN KEY (animator_id) REFERENCES animators (animator_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Фотографии (портфолио и фото с праздников) ----------
CREATE TABLE IF NOT EXISTS photos (
  photo_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  booking_id INT UNSIGNED DEFAULT NULL,
  title      VARCHAR(150) DEFAULT NULL,
  file_path  VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (photo_id),
  KEY idx_photos_user (user_id),
  CONSTRAINT fk_photos_user FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_photos_booking FOREIGN KEY (booking_id) REFERENCES bookings (booking_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Обращения с формы «Контакты» ----------
CREATE TABLE IF NOT EXISTS contact_requests (
  request_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(100) NOT NULL,
  email      VARCHAR(150) NOT NULL,
  phone      VARCHAR(30) DEFAULT NULL,
  message    TEXT NOT NULL,
  status     ENUM('new','processed') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  Демонстрационные данные
--  Пароль всех демо-пользователей: 123456
--  Пароль администратора: admin123
-- ============================================================

INSERT IGNORE INTO users (user_id, name, email, phone, password, role) VALUES
(1, 'Администратор', 'admin@shouboom.ru', '+7 (495) 000-00-01', '$2y$10$.DAqvQYC9Lk.gIZ3VKSWrOXEMXzWw.Burd.IcoZf8cQAPOYZayVhe', 'admin'),
(2, 'Алексей Смирнов', 'alexey@shouboom.ru', '+7 (916) 100-10-01', '$2y$10$D.wVWhdXrMS9jtZTY66zD.CalL2czhAV4/QGGHkZssCdOZ7GMpU6u', 'animator'),
(3, 'Екатерина Волкова', 'ekaterina@shouboom.ru', '+7 (916) 100-10-02', '$2y$10$D.wVWhdXrMS9jtZTY66zD.CalL2czhAV4/QGGHkZssCdOZ7GMpU6u', 'animator'),
(4, 'Дмитрий Орлов', 'dmitry@shouboom.ru', '+7 (916) 100-10-03', '$2y$10$D.wVWhdXrMS9jtZTY66zD.CalL2czhAV4/QGGHkZssCdOZ7GMpU6u', 'animator'),
(5, 'Ольга Соколова', 'olga@shouboom.ru', '+7 (916) 100-10-04', '$2y$10$D.wVWhdXrMS9jtZTY66zD.CalL2czhAV4/QGGHkZssCdOZ7GMpU6u', 'animator'),
(6, 'Мария Иванова', 'maria@example.ru', '+7 (916) 200-20-01', '$2y$10$D.wVWhdXrMS9jtZTY66zD.CalL2czhAV4/QGGHkZssCdOZ7GMpU6u', 'customer'),
(7, 'Дмитрий Козлов', 'dmitry.k@example.ru', '+7 (916) 200-20-02', '$2y$10$D.wVWhdXrMS9jtZTY66zD.CalL2czhAV4/QGGHkZssCdOZ7GMpU6u', 'customer'),
(8, 'Анна Петрова', 'anna@example.ru', '+7 (916) 200-20-03', '$2y$10$D.wVWhdXrMS9jtZTY66zD.CalL2czhAV4/QGGHkZssCdOZ7GMpU6u', 'customer');

INSERT IGNORE INTO animators (animator_id, user_id, specialization, experience_years, hourly_rate, rating, bio, city, is_active) VALUES
(1, 2, 'Детские праздники', 5, 2500.00, 4.8,
 'Провожу весёлые дни рождения, выпускные и семейные праздники. Работаю с детьми 3–11 лет: игры, конкурсы, аквагрим и море позитива.', 'Москва', 1),
(2, 3, 'Шоу-программы', 8, 3000.00, 5.0,
 'Организую химические шоу, шоу мыльных пузырей и научные мастер-классы. Опыт более 500 праздников.', 'Москва', 1),
(3, 4, 'Квесты', 6, 2800.00, 4.9,
 'Авторские квесты для детей и подростков: пираты, космос, детективы. Работаю в команде с помощником.', 'Москва', 1),
(4, 5, 'Детские праздники', 4, 2700.00, 4.7,
 'Аниматор-универсал: принцессы, супергерои, ростовые куклы. Легко нахожу контакт с детьми любого возраста.', 'Москва', 1);

INSERT IGNORE INTO age_groups (age_group_id, title, min_age, max_age) VALUES
(1, '3–6 лет', 3, 6),
(2, '7–11 лет', 7, 11),
(3, '12+ лет', 12, 17);

INSERT IGNORE INTO animator_age_groups (id, animator_id, age_group_id) VALUES
(1, 1, 1), (2, 1, 2),
(3, 2, 2), (4, 2, 3),
(5, 3, 2), (6, 3, 3),
(7, 4, 1), (8, 4, 2), (9, 4, 3);

INSERT IGNORE INTO programs (program_id, title, category, description, price, duration_min, image, is_active) VALUES
(1, 'Детский праздник', 'Детские праздники',
 'Классическая программа с играми, конкурсами, эстафетами и аквагримом. Подходит для дня рождения дома или в кафе.', 2500.00, 60, 'assets/img/program-party.svg', 1),
(2, 'Химическое шоу', 'Шоу-программы',
 'Безопасные опыты, дым, цветные реакции и восторг детей. Программа адаптируется под возраст участников.', 4500.00, 60, 'assets/img/program-chem.svg', 1),
(3, 'Мыльные пузыри', 'Шоу-программы',
 'Гигантские пузыри, пузыри-корабли и интерактив со зрителями. Красиво и безопасно для любого возраста.', 3500.00, 45, 'assets/img/program-bubbles.svg', 1),
(4, 'Квест «В поисках сокровищ»', 'Квесты',
 'Командный квест с загадками, картой и сундуком сокровищ. Для детей 7–12 лет, продолжительность 90 минут.', 4000.00, 90, 'assets/img/program-quest.svg', 1),
(5, 'Ростовые куклы', 'Детские праздники',
 'Любимые герои в рост, танцы и фотосессия. Отличный вариант для встречи гостей и финала праздника.', 5000.00, 60, 'assets/img/program-mascot.svg', 1),
(6, 'Новогодняя ёлка', 'Детские праздники',
 'Дед Мороз и Снегурочка, хороводы, стихи и подарки. Сценарий подбирается под возраст детей.', 5500.00, 90, 'assets/img/program-newyear.svg', 1),
(7, 'Аквагрим', 'Детские праздники',
 'Профессиональный аквагрим безопасными красками: от зайчика до супергероя.', 1500.00, 60, 'assets/img/program-faceart.svg', 1),
(8, 'Детская дискотека', 'Шоу-программы',
 'Музыка, свет, танцевальные конкурсы и ведущий. Финальный блок для зарядки энергии.', 3000.00, 60, 'assets/img/program-disco.svg', 1);

INSERT IGNORE INTO animator_programs (id, animator_id, program_id, price) VALUES
(1, 1, 1, 2500.00), (2, 1, 7, 1500.00), (3, 1, 6, 5500.00),
(4, 2, 2, 4500.00), (5, 2, 3, 3500.00), (6, 2, 8, 3000.00),
(7, 3, 4, 4000.00), (8, 3, 1, 2800.00),
(9, 4, 5, 5000.00), (10, 4, 1, 2700.00), (11, 4, 7, 1700.00);

-- Бронирования: часть на текущую неделю, часть в прошлом (для отзывов)
INSERT IGNORE INTO bookings
(booking_id, customer_id, animator_id, program_id, contact_name, contact_phone, contact_email,
 event_date, event_time, duration_hours, address, children_age, comment, status, total_price) VALUES
(1, 6, 1, 1, 'Мария Иванова', '+7 (916) 200-20-01', 'maria@example.ru',
 CURDATE(), '12:00:00', 1.0, 'г Москва, ул. Тверская, д. 15', '5 лет', 'День рождения сына', 'confirmed', 2500.00),
(2, 7, 2, 2, 'Дмитрий Козлов', '+7 (916) 200-20-02', 'dmitry.k@example.ru',
 DATE_ADD(CURDATE(), INTERVAL 1 DAY), '15:00:00', 1.0, 'г Москва, ул. Арбат, д. 12', '8 лет', 'Химическое шоу на выпускной', 'confirmed', 4500.00),
(3, 8, 3, 4, 'Анна Петрова', '+7 (916) 200-20-03', 'anna@example.ru',
 DATE_SUB(CURDATE(), INTERVAL 30 DAY), '11:00:00', 1.5, 'г Москва, ул. Профсоюзная, д. 56', '9 лет', 'Квест для класса', 'completed', 4000.00),
(4, 6, 1, 1, 'Мария Иванова', '+7 (916) 200-20-01', 'maria@example.ru',
 DATE_SUB(CURDATE(), INTERVAL 45 DAY), '13:00:00', 1.0, 'г Москва, ул. Тверская, д. 15', '5 лет', 'Праздник во дворе', 'completed', 2500.00),
(5, 7, 2, 3, 'Дмитрий Козлов', '+7 (916) 200-20-02', 'dmitry.k@example.ru',
 DATE_SUB(CURDATE(), INTERVAL 20 DAY), '16:00:00', 1.0, 'г Москва, Кутузовский пр-кт, д. 24', '6 лет', 'Шоу пузырей', 'completed', 3500.00),
(6, 8, 1, 6, 'Анна Петрова', '+7 (916) 200-20-03', 'anna@example.ru',
 DATE_ADD(CURDATE(), INTERVAL 10 DAY), '18:00:00', 1.5, 'г Москва, ул. Мясницкая, д. 20', '4 года', 'Новогодняя ёлка', 'pending', 5500.00),
(7, 6, 4, 5, 'Мария Иванова', '+7 (916) 200-20-01', 'maria@example.ru',
 DATE_ADD(CURDATE(), INTERVAL 2 DAY), '12:00:00', 1.0, 'г Москва, ул. Пятницкая, д. 25', '3 года', 'Ростовые куклы', 'pending', 5000.00),
(8, 7, 3, 4, 'Дмитрий Козлов', '+7 (916) 200-20-02', 'dmitry.k@example.ru',
 DATE_ADD(CURDATE(), INTERVAL 3 DAY), '14:00:00', 1.5, 'г Москва, ул. Новый Арбат, д. 8', '10 лет', 'Квест на день рождения', 'confirmed', 4000.00),
(9, 8, 4, 1, 'Анна Петрова', '+7 (916) 200-20-03', 'anna@example.ru',
 DATE_SUB(CURDATE(), INTERVAL 60 DAY), '12:00:00', 1.0, 'г Москва, ул. Земляной Вал, д. 27', '6 лет', 'Праздник в детском саду', 'completed', 2700.00);

INSERT IGNORE INTO reviews (review_id, booking_id, customer_id, animator_id, rating, text) VALUES
(1, 3, 8, 3, 5, 'Квест прошёл на одном дыхании! Дети были в восторге, а родители — спокойны. Дмитрий всё организовал чётко.'),
(2, 4, 6, 1, 5, 'Аниматор Алексей просто волшебник! Дети были в восторге. Обязательно закажем ещё!'),
(3, 5, 7, 2, 5, 'Мыльные пузыри — это нечто! Дети до сих пор вспоминают. Спасибо ШоуБум!'),
(4, 9, 8, 4, 4, 'Ольга быстро нашла общий язык с детьми, программа была весёлой. Немного задержались с началом, но в целом отлично.');

INSERT IGNORE INTO photos (photo_id, user_id, booking_id, title, file_path) VALUES
(1, 6, 4, 'День рождения сына', 'uploads/photos/seed-1.svg'),
(2, 7, 5, 'Шоу мыльных пузырей', 'uploads/photos/seed-2.svg'),
(3, 8, 3, 'Квест для класса', 'uploads/photos/seed-3.svg'),
(4, 2, NULL, 'Праздник в парке', 'uploads/photos/seed-4.svg'),
(5, 3, NULL, 'Химическое шоу', 'uploads/photos/seed-5.svg'),
(6, 4, NULL, 'Квест «Пираты»', 'uploads/photos/seed-6.svg'),
(7, 5, NULL, 'Ростовые куклы', 'uploads/photos/seed-7.svg');

INSERT IGNORE INTO contact_requests (request_id, name, email, phone, message, status) VALUES
(1, 'Ирина', 'irina@example.ru', '+7 (916) 300-30-01', 'Здравствуйте! Хотим заказать аниматора на 20 декабря, ребёнку 6 лет.', 'new'),
(2, 'Павел', 'pavel@example.ru', '+7 (916) 300-30-02', 'Подскажите, возможно ли провести химическое шоу в школе для 25 детей?', 'processed');

-- ============================================================
--  ПРЕДСТАВЛЕНИЯ (5 запросов из технического задания)
-- ============================================================

-- Запрос 1. Аниматоры по специализации
CREATE OR REPLACE VIEW view_animators_by_specialization AS
SELECT a.animator_id,
       u.name AS animator_name,
       a.specialization,
       a.experience_years,
       a.hourly_rate,
       ROUND(COALESCE((SELECT AVG(r.rating) FROM reviews r WHERE r.animator_id = a.animator_id), a.rating), 1) AS avg_rating,
       (SELECT COUNT(*) FROM animator_programs ap WHERE ap.animator_id = a.animator_id) AS programs_count
FROM animators a
JOIN users u ON u.user_id = a.user_id
WHERE a.is_active = 1
ORDER BY a.specialization, avg_rating DESC;

-- Запрос 2. Аниматоры для возрастной группы
CREATE OR REPLACE VIEW view_animators_by_age_group AS
SELECT a.animator_id,
       u.name AS animator_name,
       a.specialization,
       ag.title AS age_group,
       ag.min_age,
       ag.max_age,
       a.hourly_rate
FROM animators a
JOIN users u ON u.user_id = a.user_id
JOIN animator_age_groups aag ON aag.animator_id = a.animator_id
JOIN age_groups ag ON ag.age_group_id = aag.age_group_id
WHERE a.is_active = 1
ORDER BY ag.min_age, u.name;

-- Запрос 3. Доступные тематические программы
CREATE OR REPLACE VIEW view_available_programs AS
SELECT p.program_id,
       p.title,
       p.category,
       p.description,
       p.price,
       p.duration_min,
       COUNT(DISTINCT CASE WHEN a.is_active = 1 THEN ap.animator_id END) AS animators_count
FROM programs p
LEFT JOIN animator_programs ap ON ap.program_id = p.program_id
LEFT JOIN animators a ON a.animator_id = ap.animator_id
WHERE p.is_active = 1
GROUP BY p.program_id, p.title, p.category, p.description, p.price, p.duration_min
ORDER BY p.category, p.price;

-- Запрос 4. Доступные аниматоры (активные, с загрузкой на 7 дней вперёд)
CREATE OR REPLACE VIEW view_available_animators AS
SELECT a.animator_id,
       u.name AS animator_name,
       a.specialization,
       a.hourly_rate,
       ROUND(COALESCE((SELECT AVG(r.rating) FROM reviews r WHERE r.animator_id = a.animator_id), a.rating), 1) AS avg_rating,
       (SELECT COUNT(*) FROM bookings b
         WHERE b.animator_id = a.animator_id
           AND b.status IN ('pending','confirmed')
           AND b.event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS bookings_next_week
FROM animators a
JOIN users u ON u.user_id = a.user_id
WHERE a.is_active = 1
ORDER BY bookings_next_week, avg_rating DESC;

-- Запрос 5. Бронирования аниматоров на текущую неделю
CREATE OR REPLACE VIEW view_weekly_bookings AS
SELECT b.booking_id,
       b.event_date,
       b.event_time,
       b.duration_hours,
       b.status,
       b.total_price,
       b.address,
       b.animator_id,
       u.name AS animator_name,
       COALESCE(c.name, b.contact_name) AS customer_name,
       b.contact_phone,
       p.title AS program_title
FROM bookings b
JOIN animators a ON a.animator_id = b.animator_id
JOIN users u ON u.user_id = a.user_id
LEFT JOIN users c ON c.user_id = b.customer_id
LEFT JOIN programs p ON p.program_id = b.program_id
WHERE YEARWEEK(b.event_date, 1) = YEARWEEK(CURDATE(), 1)
ORDER BY b.event_date, b.event_time;

SET FOREIGN_KEY_CHECKS = 1;
