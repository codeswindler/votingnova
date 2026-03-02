#!/bin/bash
# Test USSD endpoint with POST (as Advanta might send)

echo "Testing USSD endpoint with POST request..."
echo ""

curl -X POST "http://voting.novotechafrica.co.ke:8080/api/ussd.php" \
  -d "SESSIONID=TEST123" \
  -d "USSDCODE=*519*24#" \
  -d "MSISDN=254727839315" \
  -d "INPUT=" \
  -v

echo ""
echo ""
echo "Testing with GET..."
curl "http://voting.novotechafrica.co.ke:8080/api/ussd.php?SESSIONID=TEST123&USSDCODE=*519*24#&MSISDN=254727839315&INPUT="
