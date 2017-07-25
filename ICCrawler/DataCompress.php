<?php
class DataCompress {
	public static function gzencode(&$data) {
		return gzencode ( $data );
	}
	public static $isZipCompatible = null;
	public static function zipCompatible() {
		if (self::$isZipCompatible === null) {
			if (isset ( $_SERVER ['HTTP_ACCEPT_ENCODING'] ) && strstr ( $_SERVER ['HTTP_ACCEPT_ENCODING'], 'gzip' )) {
				if (ini_get ( 'zlib.output_compression' )) {
					ini_set ( 'zlib.output_compression', 'Off' );
				}
				self::$isZipCompatible = true;
			} else {
				self::$isZipCompatible = false;
			}
		}
		return self::$isZipCompatible;
	}
	public static function gzdecode(&$data) {
		return gzinflate ( substr ( $data, 10, - 8 ) );
	}
}