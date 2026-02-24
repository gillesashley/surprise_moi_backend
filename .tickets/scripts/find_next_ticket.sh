#!/bin/bash
grep -rl '\[ \]' .tickets/*/prd.md | sort -V | head -n1