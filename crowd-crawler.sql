-- phpMyAdmin SQL Dump
-- version 4.0.8
-- http://www.phpmyadmin.net
--
-- Хост: localhost
-- Время создания: Дек 20 2013 г., 23:07
-- Версия сервера: 5.1.69
-- Версия PHP: 5.3.3

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- База данных: `crowd-sqobot`
--

-- --------------------------------------------------------

--
-- Структура таблицы `st_newmail_table`
--

CREATE TABLE IF NOT EXISTS `st_newmail_table` (
  `project_id` varchar(255) NOT NULL COMMENT 'Ссылка на проект',
  `digest` varchar(10) NOT NULL,
  UNIQUE KEY `project_id` (`project_id`,`digest`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `st_project_page`
--

CREATE TABLE IF NOT EXISTS `st_project_page` (
  `load_time` datetime NOT NULL COMMENT 'Время загрузки страницы с данными',
  `site_id` varchar(20) NOT NULL,
  `project_id` varchar(255) NOT NULL COMMENT 'Ссылка на проект',
  `name` varchar(100) NOT NULL,
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
  `latitude` decimal(15,12) DEFAULT NULL,
  `longitude` decimal(16,12) DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `short_url` varchar(100) NOT NULL,
  `full_desc` text,
  `project_json` text,
  `hourly` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Часовая рассылка',
  `daily` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Ежедневная рассылка',
  `weekly` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Еженедельная рассылка',
  `ref_page` varchar(255) DEFAULT NULL,
  `mailformed` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Флаг недооформленного проекта',
  PRIMARY KEY (`project_id`),
  KEY `country` (`country`),
  KEY `deadline` (`deadline`),
  KEY `launched_at` (`launched_at`),
  KEY `location` (`location`),
  KEY `category` (`category`),
  KEY `site_id` (`site_id`),
  KEY `hourly` (`hourly`),
  KEY `daily` (`daily`),
  KEY `weekly` (`weekly`),
  KEY `malformed` (`mailformed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `st_site_index`
--

CREATE TABLE IF NOT EXISTS `st_site_index` (
  `load_time` datetime NOT NULL COMMENT 'Время загрузки страницы с данными',
  `site_id` varchar(20) NOT NULL,
  `project_id` varchar(255) NOT NULL COMMENT 'Ссылка на проект',
  `ref_page` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`project_id`),
  UNIQUE KEY `project_id` (`project_id`),
  KEY `load_time` (`load_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Project index';

-- --------------------------------------------------------

--
-- Структура таблицы `st_users_category`
--

CREATE TABLE IF NOT EXISTS `st_users_category` (
  `ID` bigint(20) NOT NULL COMMENT 'ID пользователя',
  `site_id` varchar(20) NOT NULL,
  `category` varchar(100) NOT NULL COMMENT 'Название категории',
  UNIQUE KEY `id_cat` (`ID`,`category`,`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `wp_users`
--

CREATE TABLE IF NOT EXISTS `wp_users` (
  `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_login` varchar(60) NOT NULL DEFAULT '',
  `user_pass` varchar(64) NOT NULL DEFAULT '',
  `user_nicename` varchar(50) NOT NULL DEFAULT '',
  `user_email` varchar(100) NOT NULL DEFAULT '',
  `user_url` varchar(100) NOT NULL DEFAULT '',
  `user_registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user_activation_key` varchar(60) NOT NULL DEFAULT '',
  `user_status` int(11) NOT NULL DEFAULT '0',
  `display_name` varchar(250) NOT NULL DEFAULT '',
  `digest` varchar(10) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `user_login_key` (`user_login`),
  KEY `user_nicename` (`user_nicename`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=3 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
