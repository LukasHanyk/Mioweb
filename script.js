let formSubmitted = false;

function formatNumber(num) {
  return Number(num).toLocaleString("cs-CZ", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

async function getExchangeRate(currency) {
  if (currency === "CZK") return 1;

  const proxyUrl = "https://api.allorigins.win/raw?url=";
  const cnbUrl = "https://www.cnb.cz/en/financial_markets/foreign_exchange_market/exchange_rate_fixing/daily.txt";

  const response = await fetch(proxyUrl + encodeURIComponent(cnbUrl));
  const text = await response.text();
  const lines = text.split("\n");

  for (const line of lines) {
    const parts = line.split("|");
    if (parts.length === 5 && parts[3] === currency) {
      return parseFloat(parts[4].replace(",", "."));
    }
  }

  return 1;
}

async function updateRecap() {
  if (!formSubmitted) return;

  const quantity = parseInt(document.getElementById("quantity").value);
  const price = parseFloat(document.getElementById("price").value);
  const currency = document.getElementById("currency").value;

  const totalPriceCZK = quantity * price;
  const tax = totalPriceCZK * 0.21;
  const totalWithTax = totalPriceCZK + tax;
  const exchangeRate = await getExchangeRate(currency);
  const converted = totalWithTax / exchangeRate;

  document.getElementById("totalPriceCZK").innerText = formatNumber(totalPriceCZK) + " CZK";
  document.getElementById("taxPrice").innerText = formatNumber(tax) + " CZK";
  document.getElementById("totalPriceWithTax").innerText = formatNumber(totalWithTax) + " CZK";
  document.getElementById("exchangeRate").innerText = formatNumber(exchangeRate);
  document.getElementById("convertedPrice").innerText = formatNumber(converted) + " " + currency;
  document.getElementById("priceRecap").innerText = formatNumber(price) + " CZK";
}

  document.getElementById("orderForm").addEventListener("submit", async function (e) {
  e.preventDefault();

  const phone = document.getElementById("phone").value;
  if (!/^\d{9}$/.test(phone)) {
    document.getElementById("phoneError").innerText = "Telefon musí mít 9 číslic.";
    return;
  } else {
    document.getElementById("phoneError").innerText = "";
  }

  const firstName = document.getElementById("firstName").value;
  const lastName = document.getElementById("lastName").value;
  const email = document.getElementById("email").value;
  const product = document.getElementById("product").value;
  const quantity = parseInt(document.getElementById("quantity").value);

  formSubmitted = true;

  document.getElementById("fullName").innerText = `${firstName} ${lastName}`;
  document.getElementById("phoneRecap").innerText = phone;
  document.getElementById("emailRecap").innerText = email;
  document.getElementById("productRecap").innerText = product;
  document.getElementById("quantityRecap").innerText = formatNumber(quantity);

  document.getElementById("recap").style.display = "block";

  await updateRecap();
});

document.getElementById("price").addEventListener("input", updateRecap);
document.getElementById("quantity").addEventListener("input", updateRecap);
document.getElementById("currency").addEventListener("change", updateRecap);
