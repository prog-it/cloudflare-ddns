<?php

# Чистка кода
function Replacer($txt) {
	$txt = strip_tags($txt);
	$txt = trim($txt);
	return $txt;
}

# Дата и время для логов
function getTime() {
	return date( 'Y.m.d H:i:s', time() );
}

