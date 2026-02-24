#!/bin/bash
grep -rEc '\[(-| )\]' .tickets/*/prd.md | sort
