create database badminton_player
use badminton_player;

CREATE TABLE players (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL
);

CREATE TABLE matches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  match_date DATE
);

CREATE TABLE match_players (
  id INT AUTO_INCREMENT PRIMARY KEY,
  match_id INT,
  player_id INT,
  FOREIGN KEY (match_id) REFERENCES matches(id),
  FOREIGN KEY (player_id) REFERENCES players(id)
);

CREATE TABLE sets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  match_id INT,
  winning_team TEXT,  -- lưu dạng chuỗi JSON hoặc tên
  losing_team TEXT,
  shuttles_used INT,
  FOREIGN KEY (match_id) REFERENCES matches(id)
);

CREATE TABLE transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payer_id INT,
  receiver_id INT,
  amount DECIMAL(10,2),
  reason TEXT,
  FOREIGN KEY (payer_id) REFERENCES players(id),
  FOREIGN KEY (receiver_id) REFERENCES players(id)
);



