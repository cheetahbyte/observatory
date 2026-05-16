import http from "k6/http";
import { check, sleep } from "k6";
import { Trend, Rate } from "k6/metrics";

const appcastLatency = new Trend("appcast_latency");
const appcastFailures = new Rate("appcast_failures");

export const options = {
	thresholds: {
		http_req_failed: ["rate<0.01"],
		http_req_duration: ["p(95)<300", "p(99)<800"],
		appcast_failures: ["rate<0.01"],
		appcast_latency: ["p(95)<300"],
	},

	scenarios: {
		warmup: {
			executor: "ramping-vus",
			stages: [
				{ duration: "15s", target: 5 },
				{ duration: "15s", target: 10 },
				{ duration: "15s", target: 0 },
			],
		},

		normal_load: {
			executor: "constant-vus",
			vus: 25,
			duration: "1m",
			startTime: "45s",
		},

		spike: {
			executor: "ramping-vus",
			startTime: "1m45s",
			stages: [
				{ duration: "10s", target: 100 },
				{ duration: "20s", target: 100 },
				{ duration: "10s", target: 0 },
			],
		},
	},
};

const BASE_URL = __ENV.BASE_URL || "http://127.0.0.1:8080";

const versions = ["1.0.0", "1.0.1", "1.1.0", "1.2.0"];
const builds = ["1", "2", "3", "4"];
const macosVersions = ["14.7", "15.0", "15.1", "15.5"];
const arches = ["arm64", "x86_64"];
const languages = ["en", "de"];

function pick(values) {
	return values[Math.floor(Math.random() * values.length)];
}

function query(params) {
	return Object.entries(params)
		.map(
			([key, value]) =>
				`${encodeURIComponent(key)}=${encodeURIComponent(value)}`,
		)
		.join("&");
}

export default function () {
	const params = query({
		appVersion: pick(versions),
		appBuild: pick(builds),
		sparkleVersion: "2.7.0",
		osVersion: pick(macosVersions),
		arch: pick(arches),
		language: pick(languages),
	});

	const url = `${BASE_URL}/appcast.xml?${params}`;

	const res = http.get(url, {
		headers: {
			"User-Agent": "Kepler/1.0 Sparkle/2.7.0",
			Accept: "application/rss+xml, application/xml, text/xml",
		},
	});

	appcastLatency.add(res.timings.duration);

	const ok = check(res, {
		"status is 200": (r) => r.status === 200,
		"returns xml": (r) =>
			String(r.headers["Content-Type"] || "")
				.toLowerCase()
				.includes("xml") || String(r.body || "").includes("<rss"),
		"has sparkle appcast": (r) =>
			String(r.body || "").includes("<rss") &&
			String(r.body || "").includes("sparkle"),
	});

	appcastFailures.add(!ok);

	sleep(Math.random() * 2);
}
