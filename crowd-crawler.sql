-- phpMyAdmin SQL Dump
-- version 4.2.5
-- http://www.phpmyadmin.net
--
-- Хост: localhost
-- Время создания: Авг 09 2014 г., 10:30
-- Версия сервера: 5.5.39
-- Версия PHP: 5.4.31

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- База данных: `crowd-crawler`
--

-- --------------------------------------------------------

--
-- Структура таблицы `st_newmail_table`
--

CREATE TABLE IF NOT EXISTS `st_newmail_table` (
  `site_id` varchar(20) NOT NULL,
  `project_id` varchar(255) NOT NULL COMMENT 'Ссылка на проект',
  `digest` varchar(10) NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `st_project_page`
--

CREATE TABLE IF NOT EXISTS `st_project_page` (
  `load_time` datetime NOT NULL COMMENT 'Время загрузки страницы с данными',
  `site_id` varchar(20) NOT NULL,
  `project_id` varchar(255) NOT NULL COMMENT 'Ссылка на проект',
  `real_url` varchar(255) NOT NULL COMMENT 'Реальный URL проекта Indiegogo',
  `name` varchar(100) NOT NULL,
  `state` varchar(10) DEFAULT NULL COMMENT 'Состояние проекта',
  `blurb` varchar(255) NOT NULL,
  `avatar` varchar(200) DEFAULT NULL COMMENT 'Avatar image ref',
  `goal` int(11) NOT NULL,
  `campaign_type` varchar(20) DEFAULT NULL,
  `country` varchar(10) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `currency_symbol` varchar(4) NOT NULL,
  `currency_trailing_code` int(11) DEFAULT NULL,
  `deadline` datetime DEFAULT NULL,
  `launched_at` datetime DEFAULT NULL,
  `creator_name` varchar(50) NOT NULL,
  `location` varchar(100) NOT NULL,
  `location_url` varchar(150) NOT NULL,
  `category` varchar(100) NOT NULL,
  `short_url` varchar(100) NOT NULL,
  `full_desc` text,
  `project_json` text,
  `hourly` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Часовая рассылка',
  `daily` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Ежедневная рассылка',
  `weekly` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Еженедельная рассылка',
  `ref_page` varchar(255) DEFAULT NULL,
  `mailformed` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Флаг недооформленного проекта'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `st_project_stats`
--

CREATE TABLE IF NOT EXISTS `st_project_stats` (
  `load_time` datetime NOT NULL COMMENT 'Время загрузки страницы с данными',
  `site_id` varchar(20) NOT NULL,
  `project_id` varchar(255) NOT NULL COMMENT 'Ссылка на проект',
  `pledged` bigint(20) NOT NULL,
  `backers_count` int(11) NOT NULL,
  `comments_count` int(11) NOT NULL,
  `updates_count` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `st_site_index`
--

CREATE TABLE IF NOT EXISTS `st_site_index` (
  `load_time` datetime NOT NULL COMMENT 'Время загрузки страницы с данными',
  `site_id` varchar(20) NOT NULL,
  `project_id` varchar(255) NOT NULL COMMENT 'Ссылка на проект',
  `ref_page` varchar(255) DEFAULT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8 COMMENT='Project index';

-- --------------------------------------------------------

--
-- Структура таблицы `st_users_category`
--

CREATE TABLE IF NOT EXISTS `st_users_category` (
  `ID` bigint(20) NOT NULL COMMENT 'ID пользователя',
  `site_id` varchar(20) NOT NULL,
  `category` varchar(100) NOT NULL COMMENT 'Название категории'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `wp_users`
--

CREATE TABLE IF NOT EXISTS `wp_users` (
`ID` bigint(20) unsigned NOT NULL,
  `user_login` varchar(60) NOT NULL DEFAULT '',
  `user_pass` varchar(64) NOT NULL DEFAULT '',
  `user_nicename` varchar(50) NOT NULL DEFAULT '',
  `user_email` varchar(100) NOT NULL DEFAULT '',
  `user_url` varchar(100) NOT NULL DEFAULT '',
  `user_registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user_activation_key` varchar(60) NOT NULL DEFAULT '',
  `user_status` int(11) NOT NULL DEFAULT '0',
  `display_name` varchar(250) NOT NULL DEFAULT '',
  `digest` varchar(10) NOT NULL
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=3 ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `st_newmail_table`
--
ALTER TABLE `st_newmail_table`
 ADD UNIQUE KEY `project_id` (`project_id`,`digest`), ADD KEY `site_id` (`site_id`);

--
-- Indexes for table `st_project_page`
--
ALTER TABLE `st_project_page`
 ADD PRIMARY KEY (`project_id`), ADD KEY `country` (`country`), ADD KEY `deadline` (`deadline`), ADD KEY `launched_at` (`launched_at`), ADD KEY `location` (`location`), ADD KEY `category` (`category`), ADD KEY `site_id` (`site_id`), ADD KEY `hourly` (`hourly`), ADD KEY `daily` (`daily`), ADD KEY `weekly` (`weekly`), ADD KEY `load_time` (`load_time`), ADD KEY `real_url` (`real_url`), ADD KEY `state` (`state`);

--
-- Indexes for table `st_project_stats`
--
ALTER TABLE `st_project_stats`
 ADD PRIMARY KEY (`load_time`,`project_id`), ADD KEY `site_id` (`site_id`), ADD KEY `load_time` (`load_time`), ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `st_site_index`
--
ALTER TABLE `st_site_index`
 ADD PRIMARY KEY (`project_id`), ADD UNIQUE KEY `project_id` (`project_id`), ADD KEY `load_time` (`load_time`);

--
-- Indexes for table `st_users_category`
--
ALTER TABLE `st_users_category`
 ADD UNIQUE KEY `id_cat` (`ID`,`category`,`site_id`);

--
-- Indexes for table `wp_users`
--
ALTER TABLE `wp_users`
 ADD PRIMARY KEY (`ID`), ADD KEY `user_login_key` (`user_login`), ADD KEY `user_nicename` (`user_nicename`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `wp_users`
--
ALTER TABLE `wp_users`
MODIFY `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=3;
--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `st_project_stats`
--
ALTER TABLE `st_project_stats`
ADD CONSTRAINT `st_project_stats_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `st_project_page` (`project_id`) ON DELETE CASCADE ON UPDATE NO ACTION;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
