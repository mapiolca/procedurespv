-- Native Agenda events exposed by ProceduresPV.
-- Business transitions use the UPDATE trigger with details in the object context.
-- UPDATE keeps existing installations aligned; INSERT registers missing events.

UPDATE llx_c_action_trigger
SET elementtype = 'procedurespv_raccordement@procedurespv',
	contexts = 'agenda',
	label = 'Create a photovoltaic grid connection request',
	description = 'Create a native Agenda event when a grid connection request is created.',
	rang = 45000901
WHERE code = 'PVPROC_RACCORDEMENT_CREATE';

INSERT INTO llx_c_action_trigger (elementtype, code, contexts, label, description, rang)
SELECT 'procedurespv_raccordement@procedurespv', 'PVPROC_RACCORDEMENT_CREATE', 'agenda', 'Create a photovoltaic grid connection request', 'Create a native Agenda event when a grid connection request is created.', 45000901
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'PVPROC_RACCORDEMENT_CREATE');

UPDATE llx_c_action_trigger
SET elementtype = 'procedurespv_raccordement@procedurespv',
	contexts = 'agenda',
	label = 'Update a photovoltaic grid connection request',
	description = 'Create a native Agenda event for a user action that updates a grid connection request or one of its related records.',
	rang = 45000902
WHERE code = 'PVPROC_RACCORDEMENT_UPDATE';

INSERT INTO llx_c_action_trigger (elementtype, code, contexts, label, description, rang)
SELECT 'procedurespv_raccordement@procedurespv', 'PVPROC_RACCORDEMENT_UPDATE', 'agenda', 'Update a photovoltaic grid connection request', 'Create a native Agenda event for a user action that updates a grid connection request or one of its related records.', 45000902
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'PVPROC_RACCORDEMENT_UPDATE');

UPDATE llx_c_action_trigger
SET elementtype = 'procedurespv_raccordement@procedurespv',
	contexts = 'agenda',
	label = 'Delete a photovoltaic grid connection request',
	description = 'Create a native Agenda event when a grid connection request is deleted.',
	rang = 45000903
WHERE code = 'PVPROC_RACCORDEMENT_DELETE';

INSERT INTO llx_c_action_trigger (elementtype, code, contexts, label, description, rang)
SELECT 'procedurespv_raccordement@procedurespv', 'PVPROC_RACCORDEMENT_DELETE', 'agenda', 'Delete a photovoltaic grid connection request', 'Create a native Agenda event when a grid connection request is deleted.', 45000903
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'PVPROC_RACCORDEMENT_DELETE');
