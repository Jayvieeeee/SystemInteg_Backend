<?php
require_once __DIR__ . '/BaseMiddleware.php';

class SanitizeMiddleware extends BaseMiddleware {
    public function handle($request) {

        foreach($request as $key => $value) {
            if(is_string($value)) {
                $request[$key] = htmlspecialchars(strip_tags($value));
            }
        }
        return $request; 
    }
}

?>
