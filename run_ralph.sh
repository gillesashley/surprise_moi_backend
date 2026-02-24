#!/bin/bash

[[ -d .tmp ]] || mkdir -p .tmp;
[[ -f .tmp/models.txt ]] || opencode models | grep -iE "(openrouter).*(free)" | xargs  -P10 -I{} bash -c 'echo "$( [[ -n `opencode run -m {} \"say hello\" 2>/dev/null | grep -ivE "(error|build)" `  ]] && echo {} || echo 'error' )"' |grep -v error


# --- Dry-run flag ---
if [[ "${1:-}" == "--dry-run" ]]; then
    DRY_RUN=1
else
    DRY_RUN=0
fi

# --- Log file ---
LOG_FILE=".ralph_run.log"

log() {
    echo "[$(date -Is)] $1" | tee -a "$LOG_FILE"
}

# --- Model Definitions ---
opencode_models=(
    "opencode/big-pickle"
    "opencode/minimax-m2.1-free"
    "opencode/glm-4.7-free"
)

kilo_models=(
    "corethink:free"
    "z-ai/glm-4.7:free"
    "minimax/minimax-m2.1:free"
    "moonshotai/kimi-k2.5:free"
)

gemini_models=(
    "gemini-2.5-pro"
    "gemini-2.5-flash"
)

# --- Dynamically load additional models from .tmp/models.txt ---
if [[ -f ".tmp/models.txt" ]]; then
    while IFS= read -r model || [[ -n "$model" ]]; do
        [[ -z "$model" || "$model" == \#* ]] && continue
        opencode_models+=("$model")
        kilo_models+=("$model")
    done < ".tmp/models.txt"
    log "Loaded additional models from .tmp/models.txt"
fi

# --- Trap Interrupts ---
cleanup_and_exit() {
    log "Recieved interrupt (SIGINT/SIGTERM). Exiting script..."
    exit 1
}
trap cleanup_and_exit SIGINT SIGTERM

# --- External counter storage ---
COUNTER_FILE=".ralph_counter"
CONFIG_FILE=".ralph_config"

if [[ ! -f "$CONFIG_FILE" ]]; then
    echo "initialized" > "$CONFIG_FILE"
    log "Created $CONFIG_FILE"
fi

if [[ ! -f "$COUNTER_FILE" ]]; then
    echo "opencode_counter=0
kilo_counter=0
gemini_counter=0" > "$COUNTER_FILE"
    log "Created $COUNTER_FILE with all counters at 0"
fi

# Helper: load counters from external file
load_counters() {
    # shellcheck disable=SC1090
    if [[ -f "$COUNTER_FILE" ]]; then
        source "$COUNTER_FILE"
    else
        opencode_counter=0; kilo_counter=0; gemini_counter=0
    fi
}

# Helper: save counters to external file
save_counters() {
    cat > "$COUNTER_FILE" <<EOF
opencode_counter=${opencode_counter}
kilo_counter=${kilo_counter}
gemini_counter=${gemini_counter}
EOF
}

# Initial load
load_counters

if [[ "$DRY_RUN" -eq 1 ]]; then
    log "=== DRY-RUN MODE — no commands will be executed ==="
fi

log "Loaded counters: opencode=$opencode_counter, kilo=$kilo_counter, gemini=$gemini_counter"

while (( $(grep -r '\[[ -]\]' .tickets/ | wc -l) > 0)); do
    log "--- session starting ---"

    # Refresh counters
    load_counters
    log "Counters: opencode=$opencode_counter, kilo=$kilo_counter, gemini=$gemini_counter"

    # --- Opencode ---
    idx=$((opencode_counter % ${#opencode_models[@]}))
    model="${opencode_models[$idx]}"
    if [[ "$DRY_RUN" -eq 1 ]]; then
        log "DRY-RUN opencode [$idx]: $model — skipped"
    else
        log "Running opencode [$idx]: $model"
        timeout 30m opencode run 'read and proceed with instructions in ./ralphy.md' -m "$model" --agent build --variant max -f AGENTS.md .tickets/AGENTS.md
        log "Opencode finished (exit $?)"
    fi
    ((opencode_counter++))
    save_counters
    log "opencode_counter now $opencode_counter"

    # --- Kilo ---
    idx=$((kilo_counter % ${#kilo_models[@]}))
    model="${kilo_models[$idx]}"
    if [[ "$DRY_RUN" -eq 1 ]]; then
        log "DRY-RUN kilo [$idx]: $model — skipped"
    else
        log "Running kilo [$idx]: $model"
        timeout 30m kilo run --nosplash -a --yolo -m code -M "$model" 'read and proceed with instructions in ./ralphy.md'
        log "Kilo finished (exit $?)"
    fi
    ((kilo_counter++))
    save_counters
    log "kilo_counter now $kilo_counter"

    # --- Gemini ---
    idx=$((gemini_counter % ${#gemini_models[@]}))
    model="${gemini_models[$idx]}"
    if [[ "$DRY_RUN" -eq 1 ]]; then
        log "DRY-RUN gemini [$idx]: $model — skipped"
    else
        log "Running gemini [$idx]: $model"
        timeout 30m gemini -m "$model" -yo text -p 'read and proceed with instructions in ./ralphy.md'
        log "Gemini finished (exit $?)"
    fi
    ((gemini_counter++))
    save_counters
    log "gemini_counter now $gemini_counter"

    # Wait 10 minutes
    if [[ "$DRY_RUN" -eq 1 ]]; then
        log "DRY-RUN: would sleep 600s — skipping"
        log "--- session ended (dry-run) ---"
        break
    else
        log "Sleeping 600s..."
        sleep 600
        log "--- session ended ---"
    fi
done
