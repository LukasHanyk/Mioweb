<?php declare(strict_types=1); ?>

<?php

// Funkce pro validaci textových vstupů (pro jméno, produkt, měnu apod.)
function validateText($data, bool $allowEmpty = false) {
    $data = stripslashes(trim($data));
    if (!$allowEmpty && empty($data)) {
        return false;
    }
    return htmlspecialchars($data);
}

// Funkce pro validaci číselných vstupů (cena, počet)
function validateNumber($data, bool $isInt = false) {
    $data = filter_var(trim($data), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    if ($isInt) {
        return filter_var($data, FILTER_VALIDATE_INT) !== false ? (int)$data : false;
    }
    return filter_var($data, FILTER_VALIDATE_FLOAT) !== false ? (float)$data : false;
}

// Funkce pro validaci telefonního čísla
function validatePhone($data) {
    $data = stripslashes(trim($data));
    $pattern = '/^\+?\d{1,4}?[-.\s]?\(?\d{1,3}?\)?[-.\s]?\d{1,4}[-.\s]?\d{1,4}[-.\s]?\d{1,9}$/';
    return preg_match($pattern, $data) ? $data : false;
}

// Funkce pro validaci e-mailu
function validateEmail($data) {
    return filter_var(trim($data), FILTER_VALIDATE_EMAIL);
}

// Funkce pro validaci měny
function validateCurrency($data) {
    $validCurrencies = ['EUR', 'USD', 'GBP']; // Měny, které podporujeme
    return in_array(strtoupper(trim($data)), $validCurrencies) ? strtoupper(trim($data)) : false;
}

// Funkce pro načtení kurzovního lístku ČNB
function getExchangeRates() {
    $url = 'https://www.cnb.cz/cs/financni-trhy/devizovy-trh/kurzy-devizoveho-trhu/kurzy-devizoveho-trhu/denni_kurz.txt';
    $content = file_get_contents($url);
    if ($content === false) {
        return false; // Nepodařilo se načíst data
    }
    return parseExchangeRates($content);
}

function parseExchangeRates($content) {
    $lines = explode("\n", $content);
    $rates = [];
    for ($i = 2; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (!empty($line)) {
            $parts = explode('|', $line);
            if (count($parts) === 5) {
                $currency = trim($parts[3]);
                $amount = intval(trim($parts[2]));
                $rate = floatval(str_replace(',', '.', trim($parts[4])));
                $rates[$currency] = $rate / $amount;
            }
        }
    }
    return $rates;
}

// Nastavení DPH
$dphRate = 0.21; // 21%

// Zpracování odeslaného formuláře
if (filter_input(INPUT_SERVER, "REQUEST_METHOD") === "POST") {
    // Validace vstupů pomocí nových funkcí
    $name = validateText(filter_input(INPUT_POST, "name"));
    $email = validateEmail(filter_input(INPUT_POST, "email"));
    $phone = validatePhone(filter_input(INPUT_POST, "phone"));
    $product = validateText(filter_input(INPUT_POST, "product"));
    $price = validateNumber(filter_input(INPUT_POST, "price"));
    $quantity = validateNumber(filter_input(INPUT_POST, "quantity"), true);  // Integer validation pro počet
    $currency = validateCurrency(filter_input(INPUT_POST, "currency"));

    // Uchovávání chyb
    $errors = [];

    if (!$name) {
        $errors["name"] = "Jméno je povinné.";
    }
    if (!$email) {
        $errors["email"] = "Neplatný formát e-mailu.";
    }
    if (!$phone) {
        $errors["phone"] = "Neplatné telefonní číslo.";
    }
    if (!$product) {
        $errors["product"] = "Produkt je povinný.";
    }
    if ($price === false || $price <= 0) {
        $errors["price"] = "Cena musí být kladné číslo.";
    }
    if ($quantity === false || $quantity <= 0) {
        $errors["quantity"] = "Počet kusů musí být kladné celé číslo.";
    }
    if (!$currency) {
        $errors["currency"] = "Měna není platná.";
    }

    // Pokud nejsou chyby, pokračujeme v výpočtech
    if (empty($errors)) {
        $totalPrice = $price * $quantity;
        $dphAmount = $totalPrice * $dphRate;
        $priceWithDph = $totalPrice + $dphAmount;

        $exchangeRates = getExchangeRates();
        $convertedPrice = null;
        $conversionRate = null;

        if ($exchangeRates && isset($exchangeRates[$currency])) {
            $conversionRate = $exchangeRates[$currency];
            $convertedPrice = round($priceWithDph / $conversionRate, 2);
        } else {
            $errors["exchange_rate"] = "Nepodařilo se načíst kurzovní lístek nebo vybraná měna není dostupná.";
        }
    }
}

?>



<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Objednávkový formulář</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }

        h1 {
            font-size: 25px;
            text-align: center;
            margin-top: 15px;
        }

        .form-container {
            max-width: 100%;
            margin: 0 auto;
            background-color: #fff;
            padding: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-size: 14px;
            box-sizing: border-box;
            margin-top: 5px;
        }

        input[type="submit"] {
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
            width: 100%;
            font-size: 14px;
            padding: 12px;
            margin-top: 10px;
            border-radius: 4px;
        }

        input[type="submit"]:hover {
            background-color: #45a049;
        }

        .error {
            color: red;
            font-size: 12px;
            margin-top: 5px;
        }

        .recap {
            margin-top: 20px;
            border: 1px solid #ccc;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 4px;
        }

        .recap h2 {
            text-align: center;
            color: #333;
        }

        .recap p {
            margin: 10px 0;
        }

        .recap strong {
            font-weight: bold;
        }
    @media (min-width: 768px) {
        
        h1 {
            font-size: 35px
        }
        
        .form-container {
            max-width: 600px;
            border-radius: 8px;
        }

        input, select {
            font-size: 16px;
        }

        input[type="submit"] {
            font-size: 16px;
            padding: 15px;
        }
    }
    </style>
</head>
<body>

    <h1>Objednávkový formulář</h1>
<div class="form-container">
    <?php if (!empty($errors)): ?>
        <div class="error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <div>
            <label for="name">Jméno:</label>
            <input type="text" id="name" name="name" autocomplete="name" value="<?php echo isset($name) ? $name : ''; ?>">
            <?php if (isset($errors["name"])): ?> <span class="error"><?php echo $errors["name"]; ?></span><?php endif; ?>
        </div>
        <br>
        <div>
            <label for="email">E-mail:</label>
            <input type="email" id="email" name="email" autocomplete="email" value="<?php echo isset($email) ? $email : ''; ?>">
            <?php if (isset($errors["email"])): ?> <span class="error"><?php echo $errors["email"]; ?></span><?php endif; ?>
        </div>
        <br>
        <div>
            <label for="phone">Telefon:</label>
            <input type="tel" id="phone" name="phone" autocomplete="tel" value="<?php echo isset($phone) ? $phone : ''; ?>">
            <?php if (isset($errors["phone"])): ?> <span class="error"><?php echo $errors["phone"]; ?></span><?php endif; ?>
        </div>
        <br>
        <div>
            <label for="product">Produkt:</label>
            <input type="text" id="product" name="product" value="<?php echo isset($product) ? $product : ''; ?>">
            <?php if (isset($errors["product"])): ?> <span class="error"><?php echo $errors["product"]; ?></span><?php endif; ?>
        </div>
        <br>
        <div>
            <label for="price">Cena za kus (Kč):</label>
            <input type="number" id="price" name="price" step="0.01" value="<?php echo isset($price) ? $price : ''; ?>" min="0.01">
            <?php if (isset($errors["price"])): ?> <span class="error"><?php echo $errors["price"]; ?></span><?php endif; ?>
        </div>
        <br>
        <div>
            <label for="quantity">Počet kusů:</label>
            <input type="number" id="quantity" name="quantity" value="<?php echo isset($quantity) ? $quantity : 1; ?>" min="1">
            <?php if (isset($errors["quantity"])): ?> <span class="error"><?php echo $errors["quantity"]; ?></span><?php endif; ?>
        </div>
        <br>
        <div>
            <label for="currency">Přepočet do měny:</label>
            <select id="currency" name="currency">
                <option value="">Vyberte měnu</option>
                <option value="EUR" <?php 
                    if (isset($currency) && $currency === 'EUR') { 
                        echo 'selected'; 
                    } 
                ?>>EUR</option>
                <option value="USD" <?php 
                    if (isset($currency) && $currency === 'USD') { 
                        echo 'selected'; 
                    } 
                ?>>USD</option>
                <option value="GBP" <?php 
                    if (isset($currency) && $currency === 'GBP') { 
                        echo 'selected'; 
                    } 
                ?>>GBP</option>
            </select>
            <?php if (isset($errors["currency"])): ?> 
                <span class="error"><?php echo $errors["currency"]; ?></span>
            <?php endif; ?>
        </div>

        <br>
        <input type="submit" value="Odeslat objednávku">
    </form>

    <?php if (isset($priceWithDph) && empty($errors)): ?>
        <div class="recap">
        <h2>Rekapitulace objednávky</h2>
        <p><strong>Jméno:</strong> <?php echo htmlspecialchars($name); ?></p>
        <p><strong>E-mail:</strong> <?php echo htmlspecialchars($email); ?></p>
        <p><strong>Telefon:</strong> <?php echo htmlspecialchars($phone); ?></p>
        <p><strong>Produkt:</strong> <?php echo htmlspecialchars($product); ?></p>
        <p><strong>Cena za kus:</strong> <span id="recapPrice"><?php echo htmlspecialchars(number_format($price, 2, ',', ' ')); ?></span> Kč</p>
        <p><strong>Počet kusů:</strong> <span id="recapQuantity"><?php echo htmlspecialchars(number_format($quantity)); ?></span></p>
        <p><strong>Celková cena bez DPH:</strong> <span id="recapTotalPrice"><?php echo htmlspecialchars(number_format($totalPrice, 2, ',', ' ')); ?></span> Kč</p>
        <p><strong>DPH (<?php echo $dphRate * 100; ?>%):</strong> <span id="recapDphAmount"><?php echo htmlspecialchars(number_format($dphAmount, 2, ',', ' ')); ?></span> Kč</p>
        <p><strong>Celková cena s DPH:</strong> <span id="recapPriceWithDph"><?php echo htmlspecialchars(number_format($priceWithDph, 2, ',', ' ')); ?></span> Kč</p>
        <?php if ($convertedPrice !== null): ?>
        <p><strong>Celková cena v <span id="recapConvertedCurrencyText"><?php echo htmlspecialchars($currency); ?></span> (kurz <span id="recapConvertedCurrencyRate"><?php echo htmlspecialchars(number_format($conversionRate, 2, ',', ' ')); ?></span>):</strong> <span id="recapConvertedPrice"><?php echo htmlspecialchars(number_format($convertedPrice, 2, ',', ' ')); ?></span> <span id="recapConvertedCurrency"><?php echo htmlspecialchars($currency); ?></span></p>
        <?php elseif (isset($errors["exchange_rate"])): ?>
            <p class="error"><?php echo $errors["exchange_rate"]; ?></p>
        <?php endif; ?>

    </div>

    <?php endif; ?>
</div>
    <script>
        const priceInput = document.getElementById('price');
        const quantityInput = document.getElementById('quantity');
        const recapTotalPrice = document.getElementById('recapTotalPrice');
        const recapDphAmount = document.getElementById('recapDphAmount');
        const recapPriceWithDph = document.getElementById('recapPriceWithDph');
        const recapConvertedPrice = document.getElementById('recapConvertedPrice'); // Přidáme pro zobrazení přepočtené ceny
        const recapCurrency = document.getElementById('currency');

        const dphRate = 0.21; // DPH 21%

        // Funkce pro formátování čísla s mezerami pro tisíce a čárkou pro desetinná místa
        function formatNumber(number) {
            // Ověříme, zda je hodnota číslo
            if (isNaN(number)) {
                return 'Neplatné číslo'; // Pokud není číslem, vrátíme zprávu
            }

            // Převedeme číslo na správný formát s dvěma desetinnými místy a čárkou
            const formattedNumber = number
                .toFixed(2)  // Zaručíme, že máme dvě desetinná místa
                .replace('.', ',')  // Změníme tečku na čárku
                .replace(/\B(?=(\d{3})+(?!\d))/g, ' ');  // Regulární výraz pro vložení mezer mezi tisíce

            return formattedNumber;
        }

        function updateTotalPrice() {
            const price = parseFloat(priceInput.value) || 0;
            const quantity = parseInt(quantityInput.value) || 1;

            // Přepočítání ceny a DPH
            const totalPrice = price * quantity;
            const dphAmount = totalPrice * dphRate;
            const priceWithDph = totalPrice + dphAmount;

            // Aktualizace rekapitulace pro celkovou cenu, DPH a cenu s DPH
            recapTotalPrice.textContent = formatNumber(totalPrice); // Používáme funkci pro formátování
            recapDphAmount.textContent = formatNumber(dphAmount); // Používáme funkci pro formátování
            recapPriceWithDph.textContent = formatNumber(priceWithDph); // Používáme funkci pro formátování

            // Pokud je vybraná měna, přepočítat i cenu do měny
            const selectedCurrency = recapCurrency.value;
            if (selectedCurrency) {
                const exchangeRates = <?php echo json_encode($exchangeRates); ?>; // Předání kurzů z PHP
                const conversionRate = exchangeRates[selectedCurrency];  // Získání aktuálního kurzu

                if (conversionRate) {
                    const convertedPrice = priceWithDph / conversionRate;

                    // Aktualizujeme rekapitulaci s přepočtenou cenou
                    recapConvertedPrice.textContent = formatNumber(convertedPrice); // Zde formátujeme číslo
                    recapConvertedCurrency.textContent = selectedCurrency; // Měna za částkou
                    recapConvertedCurrencyText.textContent = selectedCurrency; // Měna v textu

                    // Aktualizujeme kurz měny
                    recapConvertedCurrencyRate.textContent = `kurz ${formatNumber(conversionRate)}`; // Zobrazení kurzu
                } else {
                    recapConvertedPrice.textContent = 'Kurzy nejsou k dispozici';
                    recapConvertedCurrency.textContent = ''; // Pokud není kurz, neukazujeme měnu
                    recapConvertedCurrencyText.textContent = ''; // Pokud není kurz, neukazujeme měnu v textu
                    recapConvertedCurrencyRate.textContent = ''; // Pokud není kurz, neukazujeme kurz
                }
            } else {
                recapConvertedPrice.textContent = 'Vyberte měnu pro přepočet';
                recapConvertedCurrency.textContent = '';  // Pokud není vybraná měna, neukazujeme měnu
                recapConvertedCurrencyText.textContent = ''; // Pokud není vybraná měna, neukazujeme text
                recapConvertedCurrencyRate.textContent = ''; // Pokud není vybraná měna, neukazujeme kurz
            }
        }

        // Přidání event listenerů pro cenu, počet kusů a měnu
        if (priceInput && quantityInput && recapCurrency) {
            priceInput.addEventListener('input', updateTotalPrice);
            quantityInput.addEventListener('input', updateTotalPrice);
            recapCurrency.addEventListener('change', updateTotalPrice);
        }


    </script>

</body>
</html>
