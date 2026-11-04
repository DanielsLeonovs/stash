#!/bin/bash
# Lomu un tiesību tests. Palaid ar:  php -S localhost:8000 router.php  (otrā logā)  un  bash tests/test.sh
B=http://localhost:8000/api.php; J=$(mktemp -d); PASS=0; FAIL=0
call() { # $1=jar $2=action $3=json
  local tok; tok=$(curl -s -b $J/$1 "$B?action=me" | sed -n 's/.*"csrf":"\([^"]*\)".*/\1/p')
  curl -s -b $J/$1 -c $J/$1 -X POST -H "X-CSRF-Token: $tok" -d "$3" "$B?action=$2"; }
login() { curl -s -c $J/$1 -X POST -d "{\"login\":\"$2\",\"password\":\"$3\"}" "$B?action=login"; }
expect() { # $1=apraksts $2=atbilde $3=true/false
  if echo "$2" | grep -q "\"success\":$3"; then PASS=$((PASS+1)); echo "OK    $1"; else FAIL=$((FAIL+1)); echo "KĻŪDA $1 -> $2"; fi; }

login admin admin admin >/dev/null
expect "admin: izveido worker"  "$(call admin user_create '{"login":"worker1","password":"parole123","newRole":"worker"}')" true
expect "admin: izveido sorter"  "$(call admin user_create '{"login":"sorter1","password":"parole123","newRole":"sorter"}')" true
expect "admin: īsa parole noraidīta" "$(call admin user_create '{"login":"x1x","password":"123","newRole":"worker"}')" false
expect "admin: pievieno preci"  "$(call admin product_save '{"name":"Skrūve","qty":"50","location":"A-1"}')" true
expect "admin: negatīvs daudzums noraidīts" "$(call admin product_save '{"name":"Slikta","qty":"-5","location":""}')" false
expect "admin: nevar dzēst sevi" "$(call admin user_delete '{"login":"admin"}')" false

expect "reģistrācija bez lomas" "$(curl -s -X POST -d '{"login":"jauns1","password":"parole123","role":"admin"}' $B?action=register)" true
expect "konts bez lomas nevar ieiet" "$(login nr jauns1 parole123)" false
expect "nepareiza parole" "$(login bad admin nepareiza)" false

login w worker1 parole123 >/dev/null
expect "worker: izveido pasūtījumu" "$(call w order_save '{"product_id":1,"qty":"5","client":"SIA Tests"}')" true
expect "worker: maina statusu" "$(call w order_status '{"id":1,"status":"Komplektē"}')" true
expect "worker: nederīgs statuss" "$(call w order_status '{"id":1,"status":"Hack"}')" false
expect "worker: pārskats" "$(call w report '{}')" true
expect "worker: NAV lietotāju saraksta" "$(call w users_list '{}')" false
expect "worker: NAV preces dzēšanas" "$(call w product_delete '{"id":1}')" false
expect "worker: NAV izvietošanas" "$(call w place '{"id":1,"location":"B-2"}')" false

login s sorter1 parole123 >/dev/null
expect "sorter: redz preces" "$(call s products_list '{}')" true
expect "sorter: izvieto preci" "$(call s place '{"id":1,"location":"B-2-3"}')" true
expect "sorter: tukša vieta noraidīta" "$(call s place '{"id":1,"location":""}')" false
expect "sorter: NAV pasūtījumu" "$(call s order_save '{"product_id":1,"qty":"1","client":"x"}')" false
expect "sorter: NAV preču labošanas" "$(call s product_save '{"id":1,"name":"X","qty":"1","location":""}')" false
expect "sorter: NAV lietotāju pārvaldības" "$(call s user_update '{"login":"sorter1","newRole":"admin"}')" false
expect "bez pieteikšanās: nav piekļuves" "$(curl -s -X POST -d '{}' $B?action=products_list)" false
expect "bez CSRF tokena: noraidīts" "$(curl -s -b $J/admin -X POST -d '{}' $B?action=products_list)" false
echo "Izturēja: $PASS, neizturēja: $FAIL"; [ $FAIL -eq 0 ]
