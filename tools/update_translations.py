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
    "Archived": "Archiviert", "Automatic ContactPanda Business updates require a current ContactPanda Basic installation.": "Automatische Aktualisierungen für ContactPanda Business erfordern eine aktuelle Installation von ContactPanda.",
    "Back to board": "Zurück zum Board", "Back to projects": "Zurück zu den Projekten", "Business overview": "Business-Übersicht",
    "Business task": "Business-Aufgabe", "Cancel": "Abbrechen", "Close": "Schließen", "Column": "Spalte", "Comment": "Kommentar", "Comments": "Kommentare",
    "Companies": "Firmen", "Company ID": "Firmen-ID", "Completed": "Abgeschlossen", "Contact person": "Ansprechperson", "ContactPanda Business": "ContactPanda Business",
    "ContactPanda Business could not create all required tables.": "ContactPanda Business konnte nicht alle erforderlichen Tabellen erstellen.",
    "ContactPanda Business requires ContactPanda %s or newer.": "ContactPanda Business erfordert ContactPanda %s oder neuer.",
    "ContactPanda Business requires ContactPanda %1$s and ContactPanda Pro %2$s or newer.": "ContactPanda Business erfordert ContactPanda %1$s und ContactPanda Pro %2$s oder neuer.",
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

GERMAN.update({
    "%d%% match": "%d %% Übereinstimmung",
    "A Business license is required": "Eine Business-Lizenz ist erforderlich",
    "A valid ContactPanda Business license is required.": "Eine gültige ContactPanda-Business-Lizenz ist erforderlich.",
    "Activate a valid ContactPanda Business license to use projects, tasks, imports, reports, and the Business MCP interface.": "Aktivieren Sie eine gültige ContactPanda-Business-Lizenz, um Projekte, Aufgaben, Importe, Berichte und die Business-MCP-Schnittstelle zu verwenden.",
    "Add area": "Teilbereich hinzufügen", "Add as comment": "Als Kommentar hinzufügen", "All assignees": "Alle Zuständigen", "All projects": "Alle Projekte", "All statuses": "Alle Status",
    "Apply selected tasks": "Ausgewählte Aufgaben übernehmen", "Area": "Teilbereich", "Area name": "Name des Teilbereichs", "Assigned projects": "Zugeordnete Projekte", "Assignee": "Zuständig",
    "Assignee and due date": "Zuständigkeit und Fälligkeit", "Back to imports": "Zurück zu den Importen", "Back to tasks": "Zurück zu den Aufgaben", "Board filters": "Board-Filter",
    "CSV, Excel, Word, text, and MCP imports always open a review list before anything is added to the live task board.": "CSV-, Excel-, Word-, Text- und MCP-Importe öffnen immer zuerst eine Prüfliste, bevor etwas in das Aufgaben-Board übernommen wird.",
    "Color": "Farbe", "Copy of %s": "Kopie von %s", "Create a review draft from extracted document items. This does not create or modify live tasks.": "Erstellt aus erkannten Dokumenteinträgen einen Prüfentwurf. Dadurch werden keine aktiven Aufgaben angelegt oder verändert.",
    "Create new task": "Neue Aufgabe erstellen", "Created": "Erstellt", "Customer response": "Kundenrückmeldung", "Delete permanently": "Endgültig löschen", "Delete project permanently": "Projekt endgültig löschen",
    "Duplicate task": "Aufgabe duplizieren", "Enter an area name.": "Geben Sie einen Namen für den Teilbereich ein.", "Existing task": "Bestehende Aufgabe", "Import action": "Importaktion",
    "Import complete: %1$d tasks created, %2$d comments added, %3$d tasks updated.": "Import abgeschlossen: %1$d Aufgaben erstellt, %2$d Kommentare hinzugefügt, %3$d Aufgaben aktualisiert.",
    "Import drafts": "Importentwürfe", "Import tasks": "Aufgaben importieren", "List Business projects and open task counts.": "Listet Business-Projekte und die Anzahl offener Aufgaben auf.",
    "Manage project areas": "Projekt-Teilbereiche verwalten", "Move project to trash": "Projekt in den Papierkorb verschieben", "Move task to trash": "Aufgabe in den Papierkorb verschieben", "My tasks": "Meine Aufgaben",
    "No area": "Kein Teilbereich", "No import drafts are available.": "Es sind keine Importentwürfe vorhanden.", "No project is currently assigned to this customer.": "Diesem Kunden ist derzeit kein Projekt zugeordnet.", "No tasks were found.": "Es wurden keine Aufgaben gefunden.",
    "Nothing is written to the task board until you confirm this list.": "In das Aufgaben-Board wird nichts übernommen, bevor Sie diese Liste bestätigen.", "Open existing task": "Bestehende Aufgabe öffnen", "Open license settings": "Lizenzeinstellungen öffnen", "Open review": "Prüfung öffnen",
    "Original source text": "Ursprünglicher Quelltext", "PDF / print report": "PDF-/Druckbericht", "Prepare links for a customer-visible HTML/PDF print view and editable Word report.": "Bereitet Links für eine kundensichtbare HTML-/PDF-Druckansicht und einen bearbeitbaren Word-Bericht vor.",
    "Print or save as PDF": "Drucken oder als PDF speichern", "Project and area": "Projekt und Teilbereich", "Project report: %s": "Projektbericht: %s", "Provide at least one task for review.": "Stellen Sie mindestens eine Aufgabe zur Prüfung bereit.",
    "Read one project with areas and tasks.": "Liest ein Projekt mit Teilbereichen und Aufgaben.", "Read project data and prepare reviewed import drafts. Changes are applied only after manual confirmation in WordPress.": "Liest Projektdaten und bereitet geprüfte Importentwürfe vor. Änderungen werden erst nach manueller Bestätigung in WordPress übernommen.",
    "Reset filters": "Filter zurücksetzen", "Responsible person": "Verantwortliche Person", "Restore": "Wiederherstellen", "Restore project": "Projekt wiederherstellen",
    "Review corrections from documents, spreadsheets, or MCP before adding them to projects.": "Prüfen Sie Korrekturen aus Dokumenten, Tabellen oder MCP, bevor sie in Projekte übernommen werden.", "Review imported tasks": "Importierte Aufgaben prüfen",
    "Search and manage tasks across all projects.": "Suchen und verwalten Sie Aufgaben projektübergreifend.", "Search tasks": "Aufgaben durchsuchen", "Search tasks by project, area, assignee, or status.": "Sucht Aufgaben nach Projekt, Teilbereich, Zuständigkeit oder Status.",
    "Select a file to import.": "Wählen Sie eine Datei für den Import aus.", "Select project": "Projekt auswählen", "Skip": "Überspringen", "Source": "Quelle",
    "The CSV file could not be read.": "Die CSV-Datei konnte nicht gelesen werden.", "The Excel workbook could not be opened.": "Die Excel-Arbeitsmappe konnte nicht geöffnet werden.", "The PHP ZIP extension is required to import Word or Excel files.": "Für den Import von Word- oder Excel-Dateien ist die PHP-ZIP-Erweiterung erforderlich.",
    "The Word document contains no readable text.": "Das Word-Dokument enthält keinen lesbaren Text.", "The Word document could not be opened.": "Das Word-Dokument konnte nicht geöffnet werden.", "The file was parsed. Review the tasks before importing them.": "Die Datei wurde verarbeitet. Prüfen Sie die Aufgaben vor der Übernahme.",
    "The first Excel sheet could not be read.": "Das erste Excel-Tabellenblatt konnte nicht gelesen werden.", "The import draft could not be created.": "Der Importentwurf konnte nicht erstellt werden.", "The import draft does not exist.": "Der Importentwurf ist nicht vorhanden.",
    "The project and its related records were deleted permanently.": "Das Projekt und seine verknüpften Datensätze wurden endgültig gelöscht.", "The project area could not be added.": "Der Projekt-Teilbereich konnte nicht hinzugefügt werden.", "The project area was added.": "Der Projekt-Teilbereich wurde hinzugefügt.",
    "The project does not exist.": "Das Projekt ist nicht vorhanden.", "The project was moved to the trash.": "Das Projekt wurde in den Papierkorb verschoben.", "The project was restored.": "Das Projekt wurde wiederhergestellt.", "The requested project action is invalid.": "Die angeforderte Projektaktion ist ungültig.",
    "The requested task action is invalid.": "Die angeforderte Aufgabenaktion ist ungültig.", "The task does not exist.": "Die Aufgabe ist nicht vorhanden.", "The task was deleted permanently.": "Die Aufgabe wurde endgültig gelöscht.", "The task was duplicated.": "Die Aufgabe wurde dupliziert.", "The task was restored.": "Die Aufgabe wurde wiederhergestellt.",
    "This action also affects related records. Do you want to continue?": "Diese Aktion betrifft auch verknüpfte Datensätze. Möchten Sie fortfahren?", "This file type is not supported for direct import.": "Dieser Dateityp wird für den direkten Import nicht unterstützt.", "Trash": "Papierkorb",
    "Unassigned": "Nicht zugeordnet", "Update existing task": "Bestehende Aufgabe aktualisieren", "Upload and review": "Hochladen und prüfen", "Upload corrections or tasks": "Korrekturen oder Aufgaben hochladen", "Use": "Übernehmen", "View task": "Aufgabe ansehen", "Without area": "Ohne Teilbereich", "Word report": "Word-Bericht",
    "A valid ContactPanda Business license is required. Activate the license in the ContactPanda settings and try again.": "Eine gültige ContactPanda-Business-Lizenz ist erforderlich. Aktivieren Sie die Lizenz in den ContactPanda-Einstellungen und versuchen Sie es erneut.",
    "Business license required": "Business-Lizenz erforderlich", "Business MCP interface": "Business-MCP-Schnittstelle", "Created: %s": "Erstellt: %s", "No importable tasks were found in the file.": "In der Datei wurden keine importierbaren Aufgaben gefunden.", "Reopen completed task": "Erledigte Aufgabe wieder öffnen",
    "The PHP ZIP extension is required to create a Word report.": "Für die Erstellung eines Word-Berichts ist die PHP-ZIP-Erweiterung erforderlich.", "The Word report could not be created.": "Der Word-Bericht konnte nicht erstellt werden.",
    "Use a WordPress application password to read projects and tasks or to create import drafts. MCP never applies a draft without confirmation in WordPress.": "Verwenden Sie ein WordPress-Anwendungspasswort, um Projekte und Aufgaben zu lesen oder Importentwürfe anzulegen. MCP übernimmt einen Entwurf niemals ohne Bestätigung in WordPress.",
    "List Business projects": "Business-Projekte auflisten", "Optional project search term.": "Optionaler Suchbegriff für Projekte.",
    "Prepare a Business import draft": "Business-Importentwurf vorbereiten", "Prepare a Business project report": "Business-Projektbericht vorbereiten",
    "Projects, tasks, reviewed imports, and customer reports.": "Projekte, Aufgaben, geprüfte Importe und Kundenberichte.",
    "Read Business project": "Business-Projekt lesen", "Search Business tasks": "Business-Aufgaben durchsuchen",
	"Apply values to selected rows": "Werte auf ausgewählte Zeilen anwenden", "Bulk edit selected tasks": "Ausgewählte Aufgaben gemeinsam bearbeiten",
	"Do not change": "Nicht ändern", "Due date": "Fälligkeitsdatum", "Remove due date": "Fälligkeitsdatum entfernen",
	"Set due date": "Fälligkeitsdatum festlegen", "tasks selected": "Aufgaben ausgewählt",
})

GERMAN.update({
    "Back to project list": "Zurück zur Projektliste",
    "Click to add financing details.": "Klicken Sie, um Finanzierungsdetails hinzuzufügen.",
    "Click to add notes or comments.": "Klicken Sie, um Notizen oder Anmerkungen hinzuzufügen.",
    "Customer comments": "Kundenkommentare",
    "Edit value": "Wert bearbeiten",
    "No.": "Nr.",
    "No customer-visible tasks are available.": "Es sind keine kundensichtbaren Aufgaben vorhanden.",
    "Notes and comments": "Notizen und Anmerkungen",
    "Project end": "Projektende",
    "Project settings": "Projekteinstellungen",
    "Save": "Speichern",
    "Status / solution": "Status / Lösung",
    "The project field could not be saved.": "Das Projektfeld konnte nicht gespeichert werden.",
    "The project field was saved.": "Das Projektfeld wurde gespeichert.",
    "The selected project status is invalid.": "Der gewählte Projektstatus ist ungültig.",
    "This project field cannot be edited here.": "Dieses Projektfeld kann hier nicht bearbeitet werden.",
    "To-do / correction": "Aufgabe / Korrektur",
})

GERMAN.update({
    "%1$d of %2$d": "%1$d von %2$d",
    "%d files could not be uploaded.": "%d Dateien konnten nicht hochgeladen werden.",
    "%d files were added.": "%d Dateien wurden hinzugefügt.",
    "%d runs": "%d Durchläufe",
    "Activity": "Aktivität",
    "Add": "Hinzufügen",
    "Add checklist item …": "Checklistenpunkt hinzufügen …",
    "Add files": "Dateien hinzufügen",
    "Add task": "Aufgabe hinzufügen",
    "added a checklist item.": "hat einen Checklistenpunkt hinzugefügt.",
    "added a comment.": "hat einen Kommentar hinzugefügt.",
    "added files.": "hat Dateien hinzugefügt.",
    "Applied": "Durchgeführt",
    "Checklist": "Checkliste",
    "Comments & activity": "Kommentare & Aktivität",
    "Confirm the warning before running an applied import again.": "Bestätigen Sie den Warnhinweis, bevor Sie einen bereits durchgeführten Import erneut ausführen.",
    "created the task.": "hat die Aufgabe erstellt.",
    "Download file": "Datei herunterladen",
    "Edit task details": "Aufgabendetails bearbeiten",
    "Enter a checklist item.": "Geben Sie einen Checklistenpunkt ein.",
    "Enter a task title …": "Aufgabentitel eingeben …",
    "Files": "Dateien",
    "I understand the warning and want to run this import again.": "Ich habe den Warnhinweis verstanden und möchte diesen Import erneut ausführen.",
    "Last run: %1$s. Number of runs: %2$d.": "Letzter Durchlauf: %1$s. Anzahl der Durchläufe: %2$d.",
    "Mark checklist item complete": "Checklistenpunkt als erledigt markieren",
    "Mark checklist item incomplete": "Checklistenpunkt als offen markieren",
    "moved the task.": "hat die Aufgabe verschoben.",
    "moved the task to “%s”.": "hat die Aufgabe nach „%s“ verschoben.",
    "No activity has been recorded yet.": "Es wurde noch keine Aktivität aufgezeichnet.",
    "No checklist items have been added yet.": "Es wurden noch keine Checklistenpunkte hinzugefügt.",
    "No description has been added.": "Es wurde noch keine Beschreibung hinzugefügt.",
    "No files have been attached yet.": "Es wurden noch keine Dateien angehängt.",
    "One file could not be uploaded.": "Eine Datei konnte nicht hochgeladen werden.",
    "One file was added.": "Eine Datei wurde hinzugefügt.",
    "Ready for review": "Zur Prüfung bereit",
    "Remove file from task": "Datei von der Aufgabe entfernen",
    "Remove this file from the task? The file remains available in the Media Library.": "Diese Datei von der Aufgabe entfernen? Die Datei bleibt in der Mediathek verfügbar.",
    "removed a file from the task.": "hat eine Datei von der Aufgabe entfernt.",
    "Running it again can create duplicate tasks or repeat comments and updates.": "Ein erneuter Durchlauf kann doppelte Aufgaben erzeugen oder Kommentare und Aktualisierungen wiederholen.",
    "Select at least one file.": "Wählen Sie mindestens eine Datei aus.",
    "Select one or more files.": "Wählen Sie eine oder mehrere Dateien aus.",
    "Select one or more files or drag them here.": "Wählen Sie eine oder mehrere Dateien aus oder ziehen Sie sie hierher.",
    "Task title": "Aufgabentitel",
    "The checklist could not be updated.": "Die Checkliste konnte nicht aktualisiert werden.",
    "The checklist item could not be added.": "Der Checklistenpunkt konnte nicht hinzugefügt werden.",
    "The checklist item was added.": "Der Checklistenpunkt wurde hinzugefügt.",
    "The checklist was updated.": "Die Checkliste wurde aktualisiert.",
    "The file link could not be removed.": "Die Dateiverknüpfung konnte nicht entfernt werden.",
    "The file was removed from the task. It remains in the Media Library.": "Die Datei wurde von der Aufgabe entfernt. Sie bleibt in der Mediathek verfügbar.",
    "This import has already been applied.": "Dieser Import wurde bereits durchgeführt.",
    "This import has already been applied. Do you really want to run it again?": "Dieser Import wurde bereits durchgeführt. Möchten Sie ihn wirklich erneut ausführen?",
    "Unknown time": "Unbekannter Zeitpunkt",
    "updated the checklist.": "hat die Checkliste aktualisiert.",
    "updated the task.": "hat die Aufgabe aktualisiert.",
    "Upload files": "Dateien hochladen",
    "Write a comment …": "Kommentar schreiben …",
})

GERMAN.update({
    "%d project documents were added.": "%d Projektdokumente wurden hinzugefügt.",
    "Acceptance": "Abnahme", "Acceptance document": "Abnahmeprotokoll", "Add payment": "Zahlung hinzufügen",
    "Add project documents": "Projektdokumente hinzufügen", "Add project part": "Projektteil hinzufügen", "Add project step": "Projektschritt hinzufügen",
    "Amount": "Betrag", "Briefing": "Briefing", "Cancelled": "Storniert", "Click to add a description.": "Klicken Sie, um eine Beschreibung hinzuzufügen.",
    "Click to add a project description.": "Klicken Sie, um eine Projektbeschreibung hinzuzufügen.",
    "Click to add financing details and internal notes.": "Klicken Sie, um Finanzierungsdetails und interne Notizen hinzuzufügen.",
    "Contract": "Vertrag", "Correspondence": "Korrespondenz", "Currency": "Währung", "Document date": "Dokumentdatum", "Document type": "Dokumenttyp",
    "Final payment": "Schlusszahlung", "Financing and notes": "Finanzierung und Notizen", "Financing and payment plan": "Finanzierungs- und Zahlungsplan", "Financing notes": "Finanzierungsnotizen",
    "General information": "Allgemeine Informationen", "Invoice": "Rechnung", "Invoice #%d": "Rechnung Nr. %d", "Keep offers, contracts, order confirmations, invoices, and other files with their context.": "Verwalten Sie Angebote, Verträge, Auftragsbestätigungen, Rechnungen und weitere Dateien mit ihrem Zusammenhang.",
    "Linked Pro invoice": "Verknüpfte Pro-Rechnung", "No invoice linked": "Keine Rechnung verknüpft",
    "Keep project information, documents, finances, tasks, and customer-visible progress together.": "Verwalten Sie Projektinformationen, Dokumente, Finanzen, Aufgaben und den kundensichtbaren Fortschritt gemeinsam.",
    "No payments have been planned yet.": "Es wurden noch keine Zahlungen geplant.", "No project activity has been recorded yet.": "Es wurde noch keine Projektaktivität aufgezeichnet.",
    "No project documents have been added yet.": "Es wurden noch keine Projektdokumente hinzugefügt.", "No project parts have been created yet.": "Es wurden noch keine Projektteile angelegt.", "Project parts": "Projektteile", "Overdue": "Überfällig",
    "No project step": "Kein Projektschritt", "No project steps have been created yet.": "Es wurden noch keine Projektschritte angelegt.", "Notes": "Notizen",
    "Offer": "Angebot", "Order awarded": "Auftrag vergeben", "Order confirmation": "Auftragsbestätigung", "Other document": "Weiteres Dokument", "Other milestone": "Weiterer Meilenstein",
    "Paid": "Bezahlt", "Paid amount": "Bezahlter Betrag", "Paid date": "Zahlungsdatum", "Partially paid": "Teilweise bezahlt", "Partial payment": "Teilzahlung", "Payment": "Zahlung", "Payment title": "Bezeichnung der Zahlung",
    "Plan partial and final payments and record when they were paid.": "Planen Sie Teil- und Schlusszahlungen und dokumentieren Sie deren Zahlung.",
    "Please enter a payment title.": "Bitte geben Sie eine Bezeichnung für die Zahlung ein.", "Please enter a project step title.": "Bitte geben Sie einen Titel für den Projektschritt ein.",
    "Project documents": "Projektdokumente", "Project history": "Projektverlauf", "Project overview": "Projektübersicht", "Project part": "Projektteil", "Project part / step": "Projektteil / Schritt",
    "Project phase": "Projektphase", "Project start": "Projektstart", "Project steps": "Projektschritte", "Reference": "Referenz", "Related project step": "Zugehöriger Projektschritt", "Remove document from project": "Dokument aus dem Projekt entfernen",
    "Remove this document from the project? The file remains available in the Media Library.": "Dieses Dokument aus dem Projekt entfernen? Die Datei bleibt in der Mediathek verfügbar.",
    "Revised offer": "Überarbeitetes Angebot", "Save payment": "Zahlung speichern", "Save project step": "Projektschritt speichern", "Select at least one project document.": "Wählen Sie mindestens ein Projektdokument aus.",
    "Split the project into independently responsible areas.": "Teilen Sie das Projekt in eigenständig verantwortete Bereiche.", "Step type": "Art des Projektschritts", "Target completion": "Geplanter Abschluss", "Tasks and corrections": "Aufgaben und Korrekturen",
    "The document was removed from the project. The media file was retained.": "Das Dokument wurde aus dem Projekt entfernt. Die Mediendatei blieb erhalten.", "The payment could not be added.": "Die Zahlung konnte nicht hinzugefügt werden.", "The payment was added.": "Die Zahlung wurde hinzugefügt.",
    "The project document could not be removed.": "Das Projektdokument konnte nicht entfernt werden.", "The project document does not exist.": "Das Projektdokument ist nicht vorhanden.", "The project step could not be added.": "Der Projektschritt konnte nicht hinzugefügt werden.", "The project step was added.": "Der Projektschritt wurde hinzugefügt.",
    "Total budget": "Gesamtbudget", "Track offers, orders, phases, acceptance, and other milestones chronologically.": "Verfolgen Sie Angebote, Aufträge, Phasen, Abnahme und weitere Meilensteine chronologisch.", "Upload documents": "Dokumente hochladen",
    "added a payment to the plan.": "hat eine Zahlung zum Plan hinzugefügt.", "added a project step.": "hat einen Projektschritt hinzugefügt.", "added project documents.": "hat Projektdokumente hinzugefügt.", "created the project.": "hat das Projekt erstellt.", "removed a document from the project.": "hat ein Dokument aus dem Projekt entfernt.", "updated the project information.": "hat die Projektinformationen aktualisiert.",
})


def quote(value):
    return '"' + value.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n') + '"'


strings = set()
pattern = re.compile(r"(?:__|esc_html__|esc_attr__)\(\s*'((?:\\'|[^'])*)'\s*,\s*'contactpanda-business'")
for path in SOURCE.rglob("*.php"):
    strings.update(match.replace("\\'", "'") for match in pattern.findall(path.read_text(encoding="utf-8")))

LANG.mkdir(exist_ok=True)
header = "Project-Id-Version: ContactPanda Business 0.8.42\nLanguage: de_DE\nContent-Type: text/plain; charset=UTF-8\nPlural-Forms: nplurals=2; plural=(n != 1);\n"
pot = ['msgid ""', 'msgstr ""', quote("Project-Id-Version: ContactPanda Business 0.8.42\nContent-Type: text/plain; charset=UTF-8\n"), ""]
po = ['msgid ""', 'msgstr ""', quote(header), ""]
for msgid in sorted(strings):
    pot += [f"msgid {quote(msgid)}", 'msgstr ""', ""]
    po += [f"msgid {quote(msgid)}", f"msgstr {quote(GERMAN.get(msgid, msgid))}", ""]
(LANG / "contactpanda-business.pot").write_text("\n".join(pot), encoding="utf-8")
(LANG / "contactpanda-business-de_DE.po").write_text("\n".join(po), encoding="utf-8")
(LANG / "contactpanda-business-de_DE_formal.po").write_text("\n".join(po).replace("Language: de_DE\\n", "Language: de_DE_formal\\n"), encoding="utf-8")
print(f"Generated {len(strings)} Business messages")
