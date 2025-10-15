#!/usr/bin/env bash
set -euo pipefail

if [ "${EUID:-$(id -u)}" -ne 0 ]; then
  echo "This script must be run as root (use sudo)." >&2
  exit 1
fi

apt-get update

apt-get install -y \
  alsa-topology-conf \
  alsa-ucm-conf \
  dbus-user-session \
  gsettings-desktop-schemas \
  libasound2-data \
  libasound2t64 \
  libatk-bridge2.0-0t64 \
  libatk1.0-0t64 \
  libatspi2.0-0t64 \
  libcairo2 \
  libcups2t64 \
  libdconf1 \
  libdrm2 \
  libgbm1 \
  libnss3 \
  libpango-1.0-0 \
  libwayland-server0 \
  libx11-xcb1 \
  libxcb-dri3-0 \
  libxcb-present0 \
  libxcb-randr0 \
  libxcb-sync1 \
  libxcb-xfixes0 \
  libxcomposite1 \
  libxdamage1 \
  libxfixes3 \
  libxkbcommon0 \
  libxrandr2 \
  libxshmfence1 \
  xkb-data

