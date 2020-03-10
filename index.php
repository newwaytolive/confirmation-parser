<?php

/**
 * Parse confirmation message
 *
 * The structure of returned result:
 * array(3) {
 *  ["password"]=>
 *  string(4) "6062"
 *  ["account"]=>
 *  string(15) "410011995006381"
 *  ["amount"]=>
 *  float(123.2)
 * }
 * @param string $confirmationText
 * @return array|null  [password, account, amount] or null if not all of them were found
 * @return
 */
function parseConfirmationMessage(string $confirmationText): ?array
{
    $password = extractPaymentPassword($confirmationText);
    $amount = extractDebitedAmount($confirmationText);
    $account = extractReceiverAccount($confirmationText);

    if (isset($password, $account, $amount)) {
        return [
            'password' => $password,
            'account' => $account,
            'amount' => $amount,
        ];
    }

    return null;
}

/**
 * Extract password value
 *
 * Must be only one password value in a message.
 * Otherwise NULL is returned.
 * @param string $confirmationText
 * @return string|null
 */
function extractPaymentPassword(string $confirmationText): ?string
{
    /**
     * Patterns must contain named sub-pattern (?P<password>)
     * @var string[] $regularExpressions
     */
    $regularExpressions = [
        'Пароль:\s*(?P<password>\d+)',
//      'Password:\s*(?P<password>\d+)',
    ];

    $extractedPasswords = [];
    foreach ($regularExpressions as $re) {
        $reForLine = '/^\s*' . $re . '\s*?(?=\R|$)/miu'; // catching an end of all lines is the trickiest part!
        $matchesCount = preg_match_all($reForLine, $confirmationText, $matches, PREG_SET_ORDER);
        if (!$matchesCount) {
            continue;
        }
        if ($matchesCount > 1) {
            // @todo to log the input for debugging
            return null;
        }
        $extractedPasswords[] = $matches[0]['password'];
        // will use first match
        break;
    }

    if (count($extractedPasswords) == 0) {
        return null;
    }

    return $extractedPasswords[0];
}

/**
 * Extract receiver account ID
 *
 * Must be only one account ID in a message.
 * Otherwise NULL is returned.
 * @param string $confirmationText
 * @return string|null
 */
function extractReceiverAccount(string $confirmationText): ?string
{
    /**
     * Patterns must contain named sub-pattern (?P<account>
     * @var string[] $regularExpressions
     */
    $regularExpressions = [
        'Перевод\s+на\s+счет\s+(?P<account>\d{13,16})',
        'Перевод\s+на\s+счёт\s+(?P<account>\d{13,16})',
    ];

    $extractedAccounts = [];
    foreach ($regularExpressions as $re) {
        $reForLine = '/^\s*' . $re . '\s*?(?=\R|$)/miu'; // catching an end of all lines is the trickiest part!
        $matchesCount = preg_match_all($reForLine, $confirmationText, $matches, PREG_SET_ORDER);
        if (!$matchesCount) {
            continue;
        }
        if ($matchesCount > 1) {
            // @todo to log the input for debugging
            return null;
        }
        $extractedAccounts[] = $matches[0]['account'];
        // will use first match
        break;
    }

    if (count($extractedAccounts) == 0) {
        return null;
    }

    return $extractedAccounts[0];
}

/**
 * Extract debited amount
 *
 * Must be only one value in a message.
 * Otherwise NULL is returned.
 * @param string $confirmationText
 * @return float|null
 */
function extractDebitedAmount(string $confirmationText): ?float
{
    /**
     * Patterns must contain named sub-patterns for (?P<integer>), and (?P<fraction>) or (?P<fraction_natural>)
     * @var string[] $regularExpressions
     */
    $regularExpressions = [
        'Спишется\s+(?P<integer>\d{1,})(?:,?(?P<fraction>\d{0,2}))\s*(?:р|р\.|руб|руб\.|рублей|рубля|рубль)',
        'Спишется\s+(?:(?P<integer>\d{1,})\s*(?:р|р\.|руб|руб\.|рублей|рубля|рубль))?\s+(?:(?P<fraction_natural>\d{1,2})\s*(?:к|к\.|коп|коп\.|копеек|копейки|копейка))?',
        //'Списываемая сумма\s+(?P<integer>\d{1,})\.(?P<fraction>\d{1,2})р\.',
    ];

    $extractedSums = [];
    foreach ($regularExpressions as $re) {
        $reForLine = '/^\s*' . $re . '\s*?(?=\R|$)/miu'; // catching an end of all lines is the trickiest part!
        $matchesCount = preg_match_all($reForLine, $confirmationText, $matches, PREG_SET_ORDER);
        if (!$matchesCount) {
            continue;
        }
        if ($matchesCount > 1) {
            // @todo to log the input for debugging
            return null;
        }
        $integer = 0;
        $fraction = 0;
        if (isset($matches[0]['integer'])) {
            $integer = intval($matches[0]['integer']);
        }
        if (isset($matches[0]['fraction'])) {
            $fractionString = $matches[0]['fraction'];
            $fraction = intval($fractionString) / (10 ** strlen($fractionString));
        } elseif (isset($matches[0]['fraction_natural'])) {
            $fraction = intval($matches[0]['fraction_natural']) / 100;
        }
        $extractedSums[] = (float) $integer + $fraction;
        // will use first match
        break;
    }

    if (count($extractedSums) == 0) {
        return null;
    }

    return $extractedSums[0];
}
