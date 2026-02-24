#!/bin/bash
grep -rEc '\[(-| )\]' .tickets/*/prd.md | grep ':0' | sort
