/* global process */

import WebSocket from "ws";

const key = process.env.REVERB_APP_KEY || "conformancekey";
const host = process.env.TARGET_HOST || "127.0.0.1";
const socketScheme = process.env.REVERB_SCHEME || "ws";
const webScheme = process.env.WEB_SCHEME || "http";
const socketPort = process.env.REVERB_PORT || 8080;
const webPort = process.env.WEB_PORT || 8002;
const socketUrl = `${socketScheme}://${host}:${socketPort}/app/${key}?protocol=7&client=node&version=1.0`;
const triggerUrl =
    process.env.TRIGGER_URL ||
    `${webScheme}://${host}:${webPort}/conformance/broadcast`;

const fail = (message) => {
    console.error(`WebSocket conformance failed: ${message}`);
    process.exit(1);
};

const timeout = setTimeout(() => fail("timed out waiting for broadcast"), 20_000);
const socket = new WebSocket(socketUrl);

socket.on("message", async (raw) => {
    let message;

    try {
        message = JSON.parse(raw.toString());
    } catch {
        return;
    }

    if (message.event === "pusher:connection_established") {
        socket.send(JSON.stringify({ event: "pusher:subscribe", data: { channel: "conformance" } }));
    }

    if (message.event === "pusher_internal:subscription_succeeded") {
        const response = await fetch(triggerUrl);

        if (!response.ok) {
            fail(`trigger returned HTTP ${response.status}`);
        }
    }

    if (message.event === "ping") {
        clearTimeout(timeout);
        console.log("WebSocket conformance passed");
        socket.close();
    }
});

socket.on("error", (error) => fail(error.message));
