#!/bin/bash
ssh -R ldapid1.serveo.net:80:localhost:8880 serveo.net | tee storage/logs/serveo.log
