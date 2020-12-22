<?php

/**
 * 最多保存多少条测试记录
 */
const MAX_LOG_COUNT = 100;

/**
 * IP运营商解析服务：(1) ip.sb | (2) ipinfo.io （如果1解析ip异常，请切换成2）
 */
const IP_SERVICE = 'ip.sb';

/**
 * 是否允许同一IP记录多条测速结果
 */
const SAME_IP_MULTI_LOGS = false;
