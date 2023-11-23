# IP-adres en poort van je applicatie
HOST="93.119.13.121"
PORT=8080

# Het pad dat getest moet worden
TEST_PATH="/"

# Voer een HTTP-verzoek uit
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" http://${HOST}:${PORT}${TEST_PATH})

# Check of de respons 200 OK is
if [ "$RESPONSE" == "200" ]; then
    echo "Test geslaagd: HTTP-verzoek naar ${HOST}:${PORT}${TEST_PATH} gaf 200 OK"
    exit 0
else
    echo "Test mislukt: HTTP-verzoek naar ${HOST}:${PORT}${TEST_PATH} gaf ${RESPONSE}, verwacht was 200"
    exit 1
fi
