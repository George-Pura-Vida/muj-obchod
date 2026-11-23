"""Exercise actual SDK framing and tool discovery over a stdio subprocess."""
import asyncio
from pathlib import Path
import sys
import tempfile
import unittest

from mcp import ClientSession, StdioServerParameters
from mcp.client.stdio import stdio_client


class ProtocolTest(unittest.TestCase):
    def test_discovery_and_read_tools_over_stdio(self):
        async def check(directory):
            config = StdioServerParameters(command=sys.executable, args=[
                str(Path(__file__).with_name("server.py")), "--state-dir", directory + "/state",
                "--token-file", directory + "/missing-token",
            ])
            async with stdio_client(config) as (read, write):
                async with ClientSession(read, write) as client:
                    await client.initialize()
                    result = await client.list_tools()
                    tools = {tool.name: tool for tool in result.tools}
                    self.assertEqual(set(tools), {"get_context", "find_client", "save_note", "operation_status", "retry_note"})
                    self.assertTrue(tools["operation_status"].annotations.readOnlyHint)
                    self.assertFalse(tools["save_note"].annotations.readOnlyHint)
                    self.assertTrue(tools["save_note"].annotations.idempotentHint)
                    context = await client.call_tool("get_context", {})
                    self.assertFalse(context.isError)
                    self.assertEqual(context.structuredContent["timezone"], "Europe/Prague")
                    missing = await client.call_tool("operation_status", {"operation_id": "voice_test_001"})
                    self.assertEqual(missing.structuredContent["state"], "unknown")
                    # Token absence is a controlled failure, with no HTTP call.
                    blocked = await client.call_tool("find_client", {"name": "Test Testovací"})
                    self.assertFalse(blocked.structuredContent["ok"])
                    self.assertEqual(blocked.structuredContent["code"], "private_file")
        with tempfile.TemporaryDirectory() as directory:
            asyncio.run(check(directory))


if __name__ == "__main__":
    unittest.main()
