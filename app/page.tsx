"use client"

import { useEffect } from "react"

export default function Page() {
  useEffect(() => {
    // Redirect to the PHP entry point
    window.location.href = "/index.php"
  }, [])

  return (
    <div
      style={{
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        justifyContent: "center",
        minHeight: "100vh",
        fontFamily: "system-ui, -apple-system, sans-serif",
        background: "linear-gradient(135deg, #0A4D2E 0%, #1B7F4D 100%)",
        color: "white",
        padding: "20px",
      }}
    >
      <div
        style={{
          background: "white",
          color: "#0A4D2E",
          padding: "40px",
          borderRadius: "20px",
          boxShadow: "0 20px 60px rgba(0,0,0,0.3)",
          maxWidth: "500px",
          textAlign: "center",
        }}
      >
        <div
          style={{
            fontSize: "64px",
            marginBottom: "20px",
            animation: "spin 2s linear infinite",
          }}
        >
          ⚡
        </div>
        <h1
          style={{
            fontSize: "24px",
            fontWeight: "bold",
            marginBottom: "10px",
          }}
        >
          CHMSU IP System
        </h1>
        <p
          style={{
            color: "#64748B",
            marginBottom: "20px",
            fontSize: "16px",
          }}
        >
          Redirecting to login...
        </p>
        <p
          style={{
            fontSize: "14px",
            color: "#94A3B8",
          }}
        >
          If you are not redirected automatically,{" "}
          <a
            href="/index.php"
            style={{
              color: "#0A4D2E",
              fontWeight: "bold",
              textDecoration: "underline",
            }}
          >
            click here
          </a>
        </p>
      </div>
      <style>{`
        @keyframes spin {
          from { transform: rotate(0deg); }
          to { transform: rotate(360deg); }
        }
      `}</style>
    </div>
  )
}
