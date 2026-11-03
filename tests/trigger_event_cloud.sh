#!/bin/bash
set -eu

gcloud pubsub topics publish firestore-backup-event --message='{"command": "COMMAND"}'
