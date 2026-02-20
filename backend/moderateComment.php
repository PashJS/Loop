<?php
// backend/moderateComment.php - Check if comment contains banned words
function moderateComment($comment) {
    $bannedWordsFile = __DIR__ . '/../banned_words.json';
    
    if (!file_exists($bannedWordsFile)) {
        return ['blocked' => false, 'filtered' => $comment];
    }
    
    $bannedWordsData = json_decode(file_get_contents($bannedWordsFile), true);
    $bannedWords = $bannedWordsData['banned_words'] ?? [];
    
    if (empty($bannedWords)) {
        return ['blocked' => false, 'filtered' => $comment];
    }
    
    $commentLower = mb_strtolower($comment, 'UTF-8');
    $blocked = false;
    $filtered = $comment;
    
    foreach ($bannedWords as $word) {
        $wordLower = mb_strtolower($word, 'UTF-8');
        // Check if word exists in comment (whole word match)
        $pattern = '/\b' . preg_quote($wordLower, '/') . '\b/u';
        if (preg_match($pattern, $commentLower)) {
            $blocked = true;
            // Replace with asterisks
            $filtered = preg_replace($pattern, str_repeat('*', mb_strlen($word, 'UTF-8')), $filtered, -1);
        }
    }
    
    return [
        'blocked' => $blocked,
        'filtered' => $filtered,
        'original' => $comment
    ];
}

