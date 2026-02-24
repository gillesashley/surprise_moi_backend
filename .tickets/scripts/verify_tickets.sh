#!/bin/bash

for prd_file in .tickets/*/prd.md; do
    count=$(awk '/## 6. Subtask Checklist/{flag=1;next}/^## [0-9]+\. /{flag=0}flag' "$prd_file" | grep -c '\[[ -]\]')
    echo "$prd_file:$count"
done | sort
