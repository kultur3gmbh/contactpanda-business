#!/usr/bin/env python3
"""Generate the Business POT and German catalog from source strings."""

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "contactpanda-business"
LANG = SOURCE / "languages"

GERMAN = {
    "%1$d open / %2$d total": "%1$d offen / %2$d gesamt",
    "A Business task was created from the support request.": "Aus dem Hilfeersuchen wurde eine Business-Aufgabe erstellt.",
    "Agency": "Agentur", "Assign one or more customer accounts, people or companies and define their role in the project.": "Ordnen Sie ein oder mehrere Kundenkonten, Personen oder Firmen zu und legen Sie deren Rolle im Projekt fest.",
    "Actions": "Aktionen", "Active": "Aktiv", "Add comment": "Kommentar hinzufügen", "All": "Alle",
    "Archived": "Archiviert", "Automatic ContactPanda Business updates require a current ContactPanda Basic installation.": "Automatische Aktualisierungen für ContactPanda Business erfordern eine aktuelle Installation von ContactPanda Basic.",
    "Back to board": "Zurück zum Board", "Back to projects": "Zurück zu den Projekten", "Business overview": "Business-Übersicht",
    "Business task": "Business-Aufgabe", "Cancel": "Abbrechen", "Close": "Schließen", "Column": "Spalte", "Comment": "Kommentar", "Comments": "Kommentare",
    "Companies": "Firmen", "Company ID": "Firmen-ID", "Completed": "Abgeschlossen", "Contact person": "Ansprechperson", "ContactPanda Business": "ContactPanda Business",
    "ContactPanda Business could not create all required tables.": "ContactPanda Business konnte nicht alle erforderlichen Tabellen erstellen.",
    "ContactPanda Business requires ContactPanda Basic 0.8.1 or newer.": "ContactPanda Business erfordert ContactPanda Basic 0.8.1 oder neuer.",
    "Contract management": "Vertragsverwaltung", "Create a Business project before converting this support request into a task.": "Erstellen Sie zuerst ein Business-Projekt, bevor Sie dieses Hilfeersuchen in eine Aufgabe umwandeln.",
    "Create project": "Projekt erstellen", "Create task": "Aufgabe erstellen", "Create task from request": "Aufgabe aus Anfrage erstellen",
    "Created from Support Desk request #%d.": "Aus dem Hilfeersuchen #%d erstellt.", "Customer": "Kunde", "Customer account": "Kundenkonto", "Customer accounts with portal access": "Kundenkonten mit Portalzugang",
    "Customer management": "Kundenverwaltung", "Description": "Beschreibung", "Done": "Erledigt", "Due": "Fällig", "Due by": "Zu erledigen bis", "Edit project": "Projekt bearbeiten",
    "Due today": "Heute fällig", "Due tomorrow": "Morgen fällig", "High": "Hoch", "In progress": "In Bearbeitung", "Internal": "Intern",
    "Internal comment": "Interner Kommentar", "Internal only": "Nur intern", "Invalid task movement.": "Ungültige Aufgabenverschiebung.",
    "Low": "Niedrig", "Manage and move tasks": "Aufgaben verwalten und verschieben", "Manage task": "Aufgabe verwalten", "Move this task to the trash?": "Diese Aufgabe in den Papierkorb verschieben?", "Move to trash": "In den Papierkorb verschieben",
    "New": "Neu", "No comments yet.": "Noch keine Kommentare.", "No due date": "Kein Fälligkeitsdatum", "No matching records are available.": "Es sind keine passenden Datensätze verfügbar.",
    "No projects are assigned to your account.": "Ihrem Konto sind keine Projekte zugeordnet.", "No projects have been created yet.": "Es wurden noch keine Projekte erstellt.",
    "Normal": "Normal", "Not assigned": "Nicht zugeordnet", "On hold": "Ruhend", "Open": "Offen", "Other participant": "Weitere Beteiligte",
    "Open full task view": "Vollständige Aufgabenansicht öffnen", "Open projects & corrections": "Projekte & Korrekturen öffnen", "Open status": "Status in Offen", "Open task: %s": "Aufgabe öffnen: %s", "Open tasks": "Offene Aufgaben",
    "Overdue: %s": "Überfällig: %s", "Overview": "Übersicht", "Payment management": "Zahlungsverwaltung", "Person ID": "Personen-ID",
    "Plan work on a board and decide separately which tasks customers may see.": "Planen Sie Arbeiten auf einem Board und legen Sie für jede Aufgabe fest, ob Kunden sie sehen dürfen.",
    "Planned": "Geplant", "Please enter a comment.": "Bitte geben Sie einen Kommentar ein.", "Please enter a project name.": "Bitte geben Sie einen Projektnamen ein.",
    "People": "Personen", "Please enter a task title.": "Bitte geben Sie einen Aufgabentitel ein.", "Priority": "Priorität", "Progress": "Fortschritt", "Project": "Projekt",
    "Project and correction management": "Projekt- und Korrekturverwaltung", "Project color": "Projektfarbe", "Project name": "Projektname", "Project participants": "Projektbeteiligte", "Project permissions": "Projektberechtigungen", "Projects": "Projekte",
    "Projects & corrections": "Projekte & Korrekturen", "Projects, corrections, customer-visible tasks, contracts, subscriptions, and payments are separated from the Support Desk.": "Projekte, Korrekturen, kundensichtbare Aufgaben, Verträge, Abonnements und Zahlungen sind vom Support Desk getrennt.",
    "Question": "Rückfrage", "Read and write": "Lesen und schreiben", "Read only": "Nur lesen", "Review": "Zur Kontrolle", "Role in project": "Rolle im Projekt", "Save project": "Projekt speichern", "Save task": "Aufgabe speichern", "Search": "Suchen",
    "Search participants": "Beteiligte suchen", "Search projects": "Projekte durchsuchen", "Select participants": "Beteiligte auswählen", "Status": "Status", "Submit a new task": "Neue Aufgabe einreichen",
    "Submit new tasks and follow customer-visible progress.": "Reichen Sie neue Aufgaben ein und verfolgen Sie den sichtbaren Fortschritt.",
    "Submit task": "Aufgabe einreichen", "Subscription management": "Abonnementverwaltung", "Task": "Aufgabe", "Task details": "Aufgabendetails",
    "Task not found.": "Aufgabe nicht gefunden.", "Tasks": "Aufgaben", "The comment was added.": "Der Kommentar wurde hinzugefügt.",
    "The project could not be created.": "Das Projekt konnte nicht erstellt werden.", "The project could not be updated.": "Das Projekt konnte nicht aktualisiert werden.", "The project was created.": "Das Projekt wurde erstellt.", "The project was updated.": "Das Projekt wurde aktualisiert.", "The support request could not be converted.": "Das Hilfeersuchen konnte nicht umgewandelt werden.",
    "The support request could not be found.": "Das Hilfeersuchen wurde nicht gefunden.", "The task was moved to the trash.": "Die Aufgabe wurde in den Papierkorb verschoben.",
    "The task could not be moved.": "Die Aufgabe konnte nicht verschoben werden.", "The task was saved.": "Die Aufgabe wurde gespeichert.", "This project does not exist.": "Dieses Projekt existiert nicht.",
    "This task does not exist.": "Diese Aufgabe existiert nicht.", "This task is not available.": "Diese Aufgabe ist nicht verfügbar.", "Title": "Titel", "Unnamed company": "Firma ohne Namen", "Unnamed person": "Person ohne Namen", "Update task": "Aufgabe aktualisieren", "Urgent": "Dringend", "View project": "Projekt ansehen",
    "Visibility": "Sichtbarkeit", "Visible to customer": "Für Kunden sichtbar", "Waiting for customer": "Wartet auf Kundenantwort",
    "You do not have permission.": "Sie haben keine Berechtigung.",
}


def quote(value):
    return '"' + value.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n') + '"'


strings = set()
pattern = re.compile(r"(?:__|esc_html__|esc_attr__)\(\s*'((?:\\'|[^'])*)'\s*,\s*'contactpanda-business'")
for path in SOURCE.rglob("*.php"):
    strings.update(match.replace("\\'", "'") for match in pattern.findall(path.read_text(encoding="utf-8")))

LANG.mkdir(exist_ok=True)
header = "Project-Id-Version: ContactPanda Business 0.8.1\nLanguage: de_DE\nContent-Type: text/plain; charset=UTF-8\nPlural-Forms: nplurals=2; plural=(n != 1);\n"
pot = ['msgid ""', 'msgstr ""', quote("Project-Id-Version: ContactPanda Business 0.8.1\nContent-Type: text/plain; charset=UTF-8\n"), ""]
po = ['msgid ""', 'msgstr ""', quote(header), ""]
for msgid in sorted(strings):
    pot += [f"msgid {quote(msgid)}", 'msgstr ""', ""]
    po += [f"msgid {quote(msgid)}", f"msgstr {quote(GERMAN.get(msgid, msgid))}", ""]
(LANG / "contactpanda-business.pot").write_text("\n".join(pot), encoding="utf-8")
(LANG / "contactpanda-business-de_DE.po").write_text("\n".join(po), encoding="utf-8")
(LANG / "contactpanda-business-de_DE_formal.po").write_text("\n".join(po).replace("Language: de_DE\\n", "Language: de_DE_formal\\n"), encoding="utf-8")
print(f"Generated {len(strings)} Business messages")
