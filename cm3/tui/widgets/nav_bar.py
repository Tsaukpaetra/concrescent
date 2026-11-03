"""
Global navigation bar for the POS terminal.

Provides a persistent top bar with a list of admin sections and a user
indicator.  Replaces the need for a separate dashboard menu — the bar
is present on every admin screen so the operator can switch sections
at any time.

This mirrors the web app's v-navigation-drawer + v-app-bar, but
presented horizontally in the terminal so all screens share it.
"""

from __future__ import annotations

from typing import Any

from textual.app import ComposeResult
from textual.containers import Container
from textual.widgets import Button, Label, Static


class NavSection(Button):
    """A single clickable section in the navigation bar."""

    CSS = """
    NavSection {
        width: auto;
        height: 1;
        padding: 0 1;
        background: $boost;
        color: $text;
        dock: left;
        content-align: center middle;
    }

    NavSection:hover, NavSection:focus {
        background: $primary;
        color: $text;
    }

    NavSection--active {
        background: $primary;
        color: $text;
    }
    """

    def __init__(self, label: str, section_id: str, active: bool = False) -> None:
        super().__init__(label)
        self.section_id = section_id
        self.add_class("nav-section")
        if active:
            self.add_class("nav-section--active")

    def on_click(self, event) -> None:
        """Activate on mouse click."""
        self.activate()

    def activate(self) -> None:
        """Emit an activation event to the parent."""
        self.app.nav_section_selected(self.section_id)  # type: ignore[attr-defined]


class NavBar(Container):
    """Top navigation bar with section tabs."""

    DEFAULT_CSS = """
    NavBar {
        width: 100%;
        height: 3;
        dock: top;
        background: $primary;
        color: $text;
    }

    NavBar > Label {
        width: auto;
        height: 1;
        padding: 0 2;
        color: $text;
        dock: left;
    }

    NavBar > .nav-section {
        width: auto;
        height: 1;
        padding: 0 2;
        background: $boost;
        color: $text;
        dock: left;
        content-align: center middle;
        border: none;
    }

    NavBar > .nav-section--active {
        background: $primary;
        color: $text;
        border: wide $secondary;
    }

    NavBar > .nav-user {
        width: 24;
        height: 1;
        padding: 0 1;
        background: $boost;
        color: $text;
        dock: right;
        content-align: center middle;
        border: none;
    }
    """

    def __init__(
        self,
        *sections: tuple[str, str],  # (section_id, label)
        username: str = "",
        event_name: str = "",
        **kwargs: Any,
    ) -> None:
        super().__init__(**kwargs)
        self._sections = sections
        self._active_section = sections[0][0] if sections else ""
        self._username = username
        self._event_name = event_name

    def compose(self) -> Any:
        # App title / label
        yield Label("CONcrescent POS")
        # Section tabs
        for section_id, label in self._sections:
            yield NavSection(label, section_id, active=(section_id == self._active_section))
        # User indicator (future: profile menu)
        user_text = f"👤 {self._username}" if self._username else "👤 Guest"
        if self._event_name:
            user_text += f"  │  {self._event_name}"
        yield Static(user_text, id="nav-user")

    @property
    def active_section(self) -> str:
        return self._active_section

    def set_active_section(self, section_id: str) -> None:
        """Mark *section_id* as active and re-compose."""
        self._active_section = section_id
        for child in self.query(".nav-section"):
            child.remove_class("nav-section--active")
            if getattr(child, "section_id", "") == section_id:
                child.add_class("nav-section--active")

    def action_nav_section_selected(self, section_id: str) -> None:
        """Handle a section tab being activated."""
        self.set_active_section(section_id)
        self.app.nav_section_selected(section_id)  # type: ignore[attr-defined]
