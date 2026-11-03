"""
API request log screen — shown from the command palette or F1.

Displays recent HTTP calls made by the POS terminal so the operator
can verify what was actually sent to the backend.
"""

from __future__ import annotations

from textual.app import ComposeResult
from textual.binding import Binding
from textual.containers import Container, Horizontal
from textual.screen import Screen
from textual.widgets import Button, DataTable, Label, Static


class APILogScreen(Screen):
    """Read-only view of recent API calls with detail panel."""

    BINDINGS = [
        Binding("escape", "close", "Close", show=True),
    ]

    CSS = """
    APILogScreen {
        align: center middle;
        background: #0c0c0c;
        color: #cccccc;
    }

    #sys-header {
        width: 100%;
        height: 1;
        background: #cccccc;
        color: #000000;
    }

    #log-card {
        width: 100;
        height: 85%;
        min-width: 80;
        min-height: 25;
        border: thick #00aa00;
        background: #000000;
        padding: 1 2;
    }

    #log-header {
        text-align: center;
        width: 100%;
        color: #00ff00;
        margin-bottom: 1;
    }

    #log-body {
        width: 100%;
        height: 1fr;
        grid-size: 2;
        grid-columns: 2fr 1fr;
        grid-rows: 1fr;
    }

    #log-table {
        width: 100%;
        height: 1fr;
    }

    #log-empty {
        text-align: center;
        color: #888888;
        height: 3;
        content-align: center middle;
    }

    #detail-panel {
        border: round #00aa00;
        padding: 1;
    }

    #detail-title {
        text-align: center;
        color: #00aa00;
        height: 1;
    }

    #detail-content {
        width: 100%;
        height: 1fr;
    }

    #detail-footer {
        height: 3;
        margin-top: 1;
    }

    #detail-footer > Button {
        width: 100%;
    }

    #fkey-bar {
        width: 100%;
        height: 1;
        background: #000000;
        color: #00ff00;
        content-align: center middle;
    }
    """


    def compose(self) -> ComposeResult:
        yield Static(
            "  [white]─── CONcrescent POS Terminal [/white]     "
            "[white]Screen:[/white] API Request Log               ",
            id="sys-header",
        )
        with Container(id="log-card"):
            with Horizontal():
                yield Label("  API Request Log", id="log-header")
                yield Button("CLOSE", variant="default", id="close-btn")
            yield Static("", id="log-empty")
            with Container(id="log-body"):
                yield DataTable(id="log-table")
                with Container(id="detail-panel"):
                    yield Label("  Select a row to view details", id="detail-title")
                    yield Static("  ", id="detail-content")
                    with Container(id="detail-footer"):
                        yield Button("COPY TO CLIPBOARD", variant="primary", id="copy-btn")
        yield Static(
            "  [green]F1[/] API Log  [green]Esc[/] Close  "
            "[green]Enter[/] Select Row  [green]Ctrl+C[/] Quit",
            id="fkey-bar",
        )

    def on_mount(self) -> None:
        table = self.query_one("#log-table", DataTable)
        table.add_column("#", key="index", width=4)
        table.add_column("Method", key="method", width=6)
        table.add_column("Endpoint", key="endpoint", width=40)
        table.add_column("Status", key="status", width=8)
        table.add_column("View", key="view", width=6)
        table.cursor_type = "row"

        log = self.app.api.get_api_log()  # type: ignore[attr-defined]
        empty = self.query_one("#log-empty", Static)

        if not log:
            table.disabled = True
            empty.update("  No API calls recorded yet.")
            return

        table.disabled = False
        empty.update("")
        for i, entry in enumerate(log, 1):
            endpoint = entry.get("url", "").replace(self.app.api.backend_url, "", 1).lstrip("/")  # type: ignore[attr-defined]
            status = entry.get("status", "?")
            table.add_row(
                str(i),
                entry.get("method", "?"),
                endpoint,
                status,
                "[View]",
                key=str(i),
            )

    def on_button_pressed(self, event: Button.Pressed) -> None:
        if event.button.id == "close-btn":
            self.dismiss(None)

    def action_close(self) -> None:
        """Handle Escape key to dismiss the log screen."""
        self.dismiss(None)

    def on_data_table_row_selected(self, event: DataTable.RowSelected) -> None:
        """Show the full request/response for the selected row."""
        table = self.query_one("#log-table", DataTable)
        row_key = event.row_key
        idx = int(row_key.value) if hasattr(row_key, 'value') else int(row_key)
        # Get the log entry at this index (1-indexed → 0-indexed)
        log = self.app.api.get_api_log()  # type: ignore[attr-defined]
        if idx < 1 or idx > len(log):
            return
        entry = log[idx - 1]

        content = "\n"
        content += f"  URL        : {entry.get('url', '?')}\n"
        content += f"  Method     : {entry.get('method', '?')}\n"
        params = entry.get("params")
        if params:
            content += f"  Params     : {params}\n"
        content += f"  Status     : {entry.get('status', '?')}\n"
        body = entry.get("body")
        if body:
            content += f"  Response   : {body}\n"
        content += "\n  [green]Enter[/] Copy  [red]Esc[/] Close"
        detail_content = self.query_one("#detail-content", Static)
        detail_content.update(content)

    def on_button_pressed_copy_btn(self, event: Button.Pressed) -> None:  # type: ignore[override]
        """Copy selected entry details to clipboard."""
        table = self.query_one("#log-table", DataTable)
        if table.focus_id != table.id:
            # Nothing selected
            return
        # Get the currently focused row
        row_id = table.highlighted_row_key
        if row_id is None:
            return
        idx = int(row_id.value) if hasattr(row_id, 'value') else int(row_id)
        log = self.app.api.get_api_log()  # type: ignore[attr-defined]
        if idx < 1 or idx > len(log):
            return
        entry = log[idx - 1]

        lines = []
        lines.append(f"[{entry.get('method', '?')}] {entry.get('url', '?')}")
        params = entry.get("params")
        if params:
            lines.append(f"  Params: {params}")
        lines.append(f"  Status: {entry.get('status', '?')}")
        body = entry.get("body")
        if body:
            lines.append(f"  Body: {body}")

        text = "\n".join(lines)
        try:
            self.app.screen.clipboard = text
            self.notify("Copied to clipboard", severity="success")
        except Exception:
            self.notify("Could not copy to clipboard", severity="error")
